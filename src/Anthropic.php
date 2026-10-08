<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use Kipchak\Core\Interfaces;
use Kipchak\Driver\Anthropic\Library\AnthropicClient;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Http\Http;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;

final class Anthropic implements Interfaces\Driver
{
    protected static ?ContainerInterface $container = null;

    public static function initialise(ContainerInterface $container, ?string $configFileName = null): void
    {
        self::$container = $container;
        self::set($configFileName);
    }

    public static function set(?string $configFileName = null): void
    {
        $configFileName = $configFileName ?? 'kipchak.anthropic';
        $config = Config::get($configFileName);

        if (!isset($config['connections'])) {
            return;
        }

        foreach ($config['connections'] as $connectionName => $connectionDetails) {
            self::$container->set(
                'drivers.anthropic.' . $connectionName,
                static function (ContainerInterface $c) use ($connectionDetails): AnthropicClient {
                    $apiKey = $connectionDetails['api_key'] ?? '';

                    if ($apiKey === '') {
                        throw new InvalidArgumentException('Anthropic api_key is required.');
                    }

                    $factory = new HttpFactory();
                    $client = new \Anthropic\Client(
                        apiKey: $apiKey,
                        requestOptions: \Anthropic\RequestOptions::with(
                            maxRetries: isset($connectionDetails['max_retries']) ? (int) $connectionDetails['max_retries'] : null,
                            transporter: self::httpClient($connectionDetails),
                            uriFactory: $factory,
                            streamFactory: $factory,
                            requestFactory: $factory,
                        ),
                    );

                    return new AnthropicClient(
                        client:       $client,
                        defaultModel: $connectionDetails['default_model'] ?? null,
                    );
                }
            );
        }
    }

    /**
     * Guzzle options for a connection without 'http_client'. No limit on the whole request, so long streamed
     * messages are never cut off; instead, a request is abandoned after 600 seconds without receiving data,
     * which is how Symfony's client (this driver's transport before 2.10) times out.
     *
     * @return array<string, mixed>
     */
    public static function defaultHttpOptions(): array
    {
        $options = [
            'connect_timeout' => 10,
            'timeout' => 0,
            'read_timeout' => 600, // honoured by Guzzle's PHP stream handler
        ];

        // Guzzle's cURL handler ignores read_timeout; cURL's low-speed limit is the same rule.
        if (\defined('CURLOPT_LOW_SPEED_LIMIT')) {
            $options['curl'] = [\CURLOPT_LOW_SPEED_LIMIT => 1, \CURLOPT_LOW_SPEED_TIME => 600];
        }

        return $options;
    }

    /**
     * The PSR-18 client for a connection: the psr18 client named by its 'http_client' setting in kipchak.http,
     * or a Guzzle client of its own. The SDK streams only through Guzzle's client, which both are.
     */
    private static function httpClient(array $connectionDetails): ClientInterface
    {
        $name = $connectionDetails['http_client'] ?? null;

        return $name === null ? new GuzzleClient(self::defaultHttpOptions()) : Http::psr18($name);
    }

    public static function get(?string $configKey = null): AnthropicClient
    {
        return self::$container->get('drivers.anthropic.' . ($configKey ?? 'default'));
    }
}
