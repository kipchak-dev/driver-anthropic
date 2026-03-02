<?php

declare(strict_types=1);

namespace Kipchak\Driver\Anthropic\Library;

use InvalidArgumentException;

/**
 * Wraps the Anthropic SDK messages resource with default model injection.
 *
 * When a model is not provided to create() or createStream(), the connection's
 * configured default_model is used automatically. If neither is available,
 * an InvalidArgumentException is thrown.
 */
final class AnthropicMessagesResource
{
    public function __construct(
        private readonly object  $resource,
        private readonly ?string $defaultModel,
    ) {}

    /**
     * Create a message.
     *
     * @param array       $messages  Conversation messages — each entry has 'role' and 'content'.
     * @param int         $maxTokens Maximum number of tokens to generate.
     * @param string|null $model     Model to use. Falls back to the connection's default_model.
     * @param string|null $system    System prompt applied before the conversation.
     * @param array       $tools     Tool definitions for tool-use requests.
     * @param array       $extra     Any additional parameters forwarded to the API.
     */
    public function create(
        array   $messages,
        int     $maxTokens = 1024,
        ?string $model     = null,
        ?string $system    = null,
        array   $tools     = [],
        array   $extra     = [],
    ): mixed {
        return $this->resource->create(...$this->buildParams(
            messages:  $messages,
            maxTokens: $maxTokens,
            model:     $model,
            system:    $system,
            tools:     $tools,
            extra:     $extra,
        ));
    }

    /**
     * Create a streaming message.
     *
     * @param array       $messages  Conversation messages — each entry has 'role' and 'content'.
     * @param int         $maxTokens Maximum number of tokens to generate.
     * @param string|null $model     Model to use. Falls back to the connection's default_model.
     * @param string|null $system    System prompt applied before the conversation.
     * @param array       $tools     Tool definitions for tool-use requests.
     * @param array       $extra     Any additional parameters forwarded to the API.
     */
    public function createStream(
        array   $messages,
        int     $maxTokens = 1024,
        ?string $model     = null,
        ?string $system    = null,
        array   $tools     = [],
        array   $extra     = [],
    ): mixed {
        return $this->resource->createStream(...$this->buildParams(
            messages:  $messages,
            maxTokens: $maxTokens,
            model:     $model,
            system:    $system,
            tools:     $tools,
            extra:     $extra,
        ));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function buildParams(
        array   $messages,
        int     $maxTokens,
        ?string $model,
        ?string $system,
        array   $tools,
        array   $extra,
    ): array {
        $params = array_merge([
            'model'     => $model ?? $this->resolveModel(),
            'maxTokens' => $maxTokens,
            'messages'  => $messages,
        ], $extra);

        if ($system !== null) {
            $params['system'] = $system;
        }

        if (!empty($tools)) {
            $params['tools'] = $tools;
        }

        return $params;
    }

    private function resolveModel(): string
    {
        return $this->defaultModel ?? throw new InvalidArgumentException(
            'A model must be specified or default_model must be configured for this connection.'
        );
    }
}
