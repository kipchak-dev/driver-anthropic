<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic\Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Kipchak\Driver\Anthropic\Anthropic;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Http\Http;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * The driver sends its requests through Kipchak's HTTP clients: a psr18 client named in kipchak.http, or a
 * Guzzle client of its own.
 */
final class AnthropicHttpClientTest extends TestCase
{
    private ContainerInterface $container;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface, options: array}> */
    private array $sent = [];

    private function boot(array $connection, array $httpConfig = []): void
    {
        $this->container = new class implements ContainerInterface {
            private array $entries = [];
            private array $resolved = [];

            public function set(string $id, mixed $value): void
            {
                $this->entries[$id] = $value;
                unset($this->resolved[$id]);
            }

            public function get(string $id): mixed
            {
                if (!array_key_exists($id, $this->entries)) {
                    throw new class ("No entry {$id}") extends \RuntimeException implements NotFoundExceptionInterface {
                    };
                }

                return $this->resolved[$id] ??= $this->entries[$id] instanceof \Closure ? ($this->entries[$id])($this) : $this->entries[$id];
            }

            public function has(string $id): bool
            {
                return array_key_exists($id, $this->entries);
            }
        };
        $this->container->set('drivers.config', [
            'kipchak.anthropic' => ['connections' => ['default' => $connection + ['api_key' => 'sk-ant-test', 'default_model' => 'claude-sonnet-4-5']]],
            'kipchak.http' => $httpConfig,
        ]);
        (new \ReflectionProperty(Config::class, 'container'))->setValue(null, $this->container);

        Http::initialise($this->container);
        Anthropic::initialise($this->container);
    }

    private function mockClient(string $name, Response ...$responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));
        $this->container->set('drivers.http.' . $name, new GuzzleClient(['handler' => $stack]));
    }

    private function sdkOptions(): object
    {
        $client = Anthropic::get()->raw();

        return (new \ReflectionProperty(\Anthropic\Core\BaseClient::class, 'options'))->getValue($client);
    }

    private static function message(string $text): string
    {
        return json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-4-5',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 3, 'output_tokens' => 2],
        ]);
    }

    public function testRequestsGoThroughTheNamedKipchakClient(): void
    {
        $this->boot(['http_client' => 'ai'], ['ai' => ['type' => 'psr18']]);
        $this->mockClient('ai', new Response(200, ['Content-Type' => 'application/json'], self::message('Hello')));

        $message = Anthropic::get()->messages()->create(messages: [['role' => 'user', 'content' => 'Hi']]);

        $this->assertSame('Hello', $message->content[0]->text);
        $this->assertCount(1, $this->sent);
        $request = $this->sent[0]['request'];
        $this->assertSame('https://api.anthropic.com/v1/messages', (string) $request->getUri());
        $this->assertSame('sk-ant-test', $request->getHeaderLine('x-api-key'));
        $this->assertSame('claude-sonnet-4-5', json_decode((string) $request->getBody(), true)['model'], 'default model still applied');
    }

    public function testStreamingWorksThroughTheKipchakClient(): void
    {
        $this->boot(['http_client' => 'ai'], ['ai' => ['type' => 'psr18']]);
        $event = fn (string $type, array $data) => "event: {$type}\ndata: " . json_encode(['type' => $type] + $data) . "\n\n";
        $sse = $event('message_start', ['message' => json_decode(self::message(''), true)])
            . $event('content_block_start', ['index' => 0, 'content_block' => ['type' => 'text', 'text' => '']])
            . $event('content_block_delta', ['index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hel']])
            . $event('content_block_delta', ['index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'lo']])
            . $event('content_block_stop', ['index' => 0])
            . $event('message_delta', ['delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 2]])
            . $event('message_stop', []);
        $this->mockClient('ai', new Response(200, ['Content-Type' => 'text/event-stream'], $sse));

        $text = '';
        foreach (Anthropic::get()->messages()->createStream(messages: [['role' => 'user', 'content' => 'Hi']]) as $streamEvent) {
            if (($streamEvent->type ?? null) === 'content_block_delta') {
                $text .= $streamEvent->delta->text;
            }
        }

        $this->assertSame('Hello', $text);
        $this->assertTrue($this->sent[0]['options']['stream'] ?? false, 'the SDK asked Guzzle to stream');
    }

    public function testWithoutHttpClientAConnectionGetsAGuzzleClientWithIdleTimeouts(): void
    {
        $this->boot([]);

        $client = $this->sdkOptions()->transporter;
        $this->assertInstanceOf(GuzzleClient::class, $client);
        $this->assertSame(10, $client->getConfig('connect_timeout'));
        $this->assertSame(0, $client->getConfig('timeout'), 'no limit on the whole request');
        $this->assertSame(600, $client->getConfig('read_timeout'));
        $this->assertSame(600, $client->getConfig('curl')[\CURLOPT_LOW_SPEED_TIME]);
        $this->assertSame(1, $client->getConfig('curl')[\CURLOPT_LOW_SPEED_LIMIT]);
    }

    public function testMaxRetriesIsStillApplied(): void
    {
        $this->boot(['max_retries' => 5]);

        $this->assertSame(5, $this->sdkOptions()->maxRetries);
    }

    public function testANamedClientMustBePsr18(): void
    {
        $this->boot(['http_client' => 'default'], ['default' => ['type' => 'laravel']]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("kipchak.http client 'default' is of type 'laravel', not 'psr18'");
        Anthropic::get();
    }
}
