<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic\Library;

/**
 * Wraps the Anthropic SDK client and provides default model injection.
 *
 * Access the messages resource via messages() to have the configured
 * default_model automatically applied when no model is specified on a call.
 * Use raw() for direct access to the full SDK client.
 */
final class AnthropicClient
{
    public function __construct(
        private readonly \Anthropic\Client $client,
        private readonly ?string $defaultModel = null,
    ) {}

    /**
     * Returns a messages resource wrapper that injects the default model
     * when no model is specified on create() or createStream() calls.
     */
    public function messages(): AnthropicMessagesResource
    {
        return new AnthropicMessagesResource($this->client->messages, $this->defaultModel);
    }

    /**
     * The configured default model for this connection, or null if none is set.
     */
    public function defaultModel(): ?string
    {
        return $this->defaultModel;
    }

    /**
     * The underlying Anthropic SDK client for direct API access.
     */
    public function raw(): \Anthropic\Client
    {
        return $this->client;
    }
}
