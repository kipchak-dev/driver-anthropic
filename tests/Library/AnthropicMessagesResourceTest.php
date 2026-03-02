<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic\Tests\Library;

use Kipchak\Driver\Anthropic\Library\AnthropicMessagesResource;
use PHPUnit\Framework\TestCase;

final class AnthropicMessagesResourceTest extends TestCase
{
    private const MESSAGES = [['role' => 'user', 'content' => 'Hello']];

    // -------------------------------------------------------------------------
    // Default model injection
    // -------------------------------------------------------------------------

    public function testCreateInjectsDefaultModelWhenNoneSpecified(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES);

        $this->assertSame('claude-opus-4-6', $fake->lastParams['model']);
    }

    public function testCreateUsesExplicitModelOverDefault(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES, model: 'claude-haiku-4-5-20251001');

        $this->assertSame('claude-haiku-4-5-20251001', $fake->lastParams['model']);
    }

    public function testCreateStreamInjectsDefaultModel(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->createStream(messages: self::MESSAGES);

        $this->assertSame('claude-opus-4-6', $fake->lastParams['model']);
    }

    public function testNoDefaultModelAndNoExplicitModelThrows(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, null);

        $this->expectException(\InvalidArgumentException::class);

        $resource->create(messages: self::MESSAGES);
    }

    // -------------------------------------------------------------------------
    // Parameter forwarding
    // -------------------------------------------------------------------------

    public function testMaxTokensIsForwarded(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES, maxTokens: 2048);

        $this->assertSame(2048, $fake->lastParams['maxTokens']);
    }

    public function testSystemPromptIsForwardedWhenProvided(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES, system: 'You are a helpful assistant.');

        $this->assertSame('You are a helpful assistant.', $fake->lastParams['system']);
    }

    public function testSystemPromptIsOmittedWhenNull(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES);

        $this->assertArrayNotHasKey('system', $fake->lastParams);
    }

    public function testToolsAreForwardedWhenProvided(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $tools = [['name' => 'get_weather', 'description' => 'Get the weather', 'input_schema' => ['type' => 'object', 'properties' => []]]];
        $resource->create(messages: self::MESSAGES, tools: $tools);

        $this->assertSame($tools, $fake->lastParams['tools']);
    }

    public function testToolsAreOmittedWhenEmpty(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES);

        $this->assertArrayNotHasKey('tools', $fake->lastParams);
    }

    public function testExtraParamsAreMergedIntoRequest(): void
    {
        $fake     = new FakeMessages();
        $resource = new AnthropicMessagesResource($fake, 'claude-opus-4-6');

        $resource->create(messages: self::MESSAGES, extra: ['temperature' => 1.0]);

        $this->assertSame(1.0, $fake->lastParams['temperature']);
    }
}

// ---------------------------------------------------------------------------
// Test double
// ---------------------------------------------------------------------------

/**
 * Captures the parameters passed to create() and createStream().
 * Parameter names match the Anthropic SDK's named argument convention.
 */
final class FakeMessages
{
    public array $lastParams = [];
    public string $lastMethod = '';

    public function create(
        string  $model,
        int     $maxTokens,
        array   $messages,
        ?string $system      = null,
        ?array  $tools       = null,
        ?float  $temperature = null,
    ): \stdClass {
        $this->lastMethod = 'create';
        $this->lastParams = array_filter(
            compact('model', 'maxTokens', 'messages', 'system', 'tools', 'temperature'),
            fn ($v) => $v !== null,
        );

        return new \stdClass();
    }

    public function createStream(
        string  $model,
        int     $maxTokens,
        array   $messages,
        ?string $system      = null,
        ?array  $tools       = null,
        ?float  $temperature = null,
    ): \stdClass {
        $this->lastMethod = 'createStream';
        $this->lastParams = array_filter(
            compact('model', 'maxTokens', 'messages', 'system', 'tools', 'temperature'),
            fn ($v) => $v !== null,
        );

        return new \stdClass();
    }
}
