<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic;

use InvalidArgumentException;
use Kipchak\Core\Interfaces;
use Kipchak\Driver\Anthropic\Library\AnthropicClient;
use Kipchak\Driver\Config\Config;
use Psr\Container\ContainerInterface;

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

                    $args = ['apiKey' => $apiKey];

                    if (isset($connectionDetails['max_retries'])) {
                        $args['maxRetries'] = (int) $connectionDetails['max_retries'];
                    }

                    $client = new \Anthropic\Client(...$args);

                    return new AnthropicClient(
                        client:       $client,
                        defaultModel: $connectionDetails['default_model'] ?? null,
                    );
                }
            );
        }
    }

    public static function get(?string $configKey = null): AnthropicClient
    {
        return self::$container->get('drivers.anthropic.' . ($configKey ?? 'default'));
    }
}
