<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic\Tests;

use Kipchak\Driver\Anthropic\Anthropic;
use Kipchak\Driver\Anthropic\Library\AnthropicClient;
use Kipchak\Driver\Config\Config;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;

final class AnthropicDriverTest extends TestCase
{
    public function testRegistersOneContainerEntryPerConnection(): void
    {
        $container = $this->primeDriver([
            'connections' => [
                'default'   => ['api_key' => 'key-a', 'default_model' => 'claude-opus-4-6'],
                'secondary' => ['api_key' => 'key-b'],
            ],
        ]);

        $this->assertTrue($container->has('drivers.anthropic.default'));
        $this->assertTrue($container->has('drivers.anthropic.secondary'));
    }

    public function testGetReturnsAnthropicClientInstance(): void
    {
        $this->primeDriver([
            'connections' => [
                'default' => ['api_key' => 'test-key', 'default_model' => 'claude-opus-4-6'],
            ],
        ]);

        $this->assertInstanceOf(AnthropicClient::class, Anthropic::get());
        $this->assertInstanceOf(AnthropicClient::class, Anthropic::get('default'));
    }

    public function testDefaultModelIsPreservedOnClient(): void
    {
        $this->primeDriver([
            'connections' => [
                'default' => ['api_key' => 'test-key', 'default_model' => 'claude-opus-4-6'],
            ],
        ]);

        $this->assertSame('claude-opus-4-6', Anthropic::get()->defaultModel());
    }

    public function testEmptyConnectionsDoesNotRegisterAnyEntries(): void
    {
        $container = $this->primeDriver([]);

        $this->assertFalse($container->has('drivers.anthropic.default'));
    }

    public function testMissingApiKeyThrowsOnResolve(): void
    {
        $this->primeDriver([
            'connections' => [
                'default' => ['default_model' => 'claude-opus-4-6'],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        Anthropic::get('default');
    }

    public function testEmptyApiKeyThrowsOnResolve(): void
    {
        $this->primeDriver([
            'connections' => [
                'default' => ['api_key' => ''],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        Anthropic::get('default');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function primeDriver(array $config): TestContainer
    {
        $configContainer = new TestContainer();
        $configContainer->set('drivers.config', ['kipchak.anthropic' => $config]);
        $this->setStaticProperty(Config::class, 'container', $configContainer);

        $driverContainer = new TestContainer();
        Anthropic::initialise($driverContainer);

        return $driverContainer;
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $reflection = new ReflectionClass($class);
        $prop       = $reflection->getProperty($property);
        $prop->setValue(null, $value);
    }
}

// ---------------------------------------------------------------------------
// Test doubles
// ---------------------------------------------------------------------------

final class TestContainer implements ContainerInterface
{
    private array $entries = [];

    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new TestNotFoundException('Service not found: ' . $id);
        }

        $entry = $this->entries[$id];

        return is_callable($entry) ? $entry($this) : $entry;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries);
    }
}

final class TestNotFoundException extends \RuntimeException implements NotFoundExceptionInterface {}
