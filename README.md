# Kipchak Anthropic Driver

A fully typed Anthropic driver for the [Kipchak API Development Kit](https://kipchak.dev).
Built on the official [Anthropic PHP SDK](https://github.com/anthropics/anthropic-sdk-php),
it provides multiple named connections, automatic default model injection, and clean access
to the Messages API including streaming and tool use.

## Requirements

- PHP 8.4 or 8.5
- [Kipchak](https://kipchak.dev) ^2 || ^3
- `kipchak/driver-config` ^1
- `anthropic-ai/sdk` ^0.36

## Versions

- **2.0** — upgrades `anthropic-ai/sdk` to ^0.36 and adds PHP 8.5 support. The driver's config (including `max_retries`) and message API are unchanged; if you use `raw()` to call the SDK directly, review the [SDK changes](https://github.com/anthropics/anthropic-sdk-php/releases). Requires `kipchak/core: ^2 || ^3`.
- **1.x** — `anthropic-ai/sdk` ^0.6, `kipchak/core` ^2.

See the [Kipchak upgrade guide](https://kipchak.dev/docs/upgrades) for details.

## Installation

```bash
composer require kipchak/driver-anthropic
```

### Initialise

Add to your `drivers/drivers.php`:

```php
\Kipchak\Driver\Anthropic\Anthropic::initialise($container);
```

## Configuration

Create `config/kipchak.anthropic.php`:

```php
<?php

use function Kipchak\Core\env;

return [
    'connections' => [
        'default' => [
            'api_key'       => env('ANTHROPIC_API_KEY', ''),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-6'),
        ],
    ],
];
```

| Key | Required | Description |
| --- | --- | --- |
| `api_key` | Yes | Anthropic API key |
| `default_model` | No | Model injected automatically when none is specified on a call |
| `max_retries` | No | Override the SDK default of 2 automatic retries |

## Usage

```php
use Kipchak\Driver\Anthropic\Anthropic;

$anthropic = Anthropic::get();           // 'default' connection
$anthropic = Anthropic::get('secondary'); // named connection
```

### Creating a Message

```php
$response = $anthropic->messages()->create(
    messages: [
        ['role' => 'user', 'content' => 'What is the capital of France?'],
    ],
);

echo $response->content[0]->text; // 'The capital of France is Paris.'
```

The configured `default_model` is applied automatically. Pass `model:` explicitly to override:

```php
$response = $anthropic->messages()->create(
    messages:  [['role' => 'user', 'content' => 'Hello']],
    model:     'claude-haiku-4-5-20251001',
    maxTokens: 512,
    system:    'You are a concise assistant. Reply in one sentence.',
);
```

### Multi-Turn Conversations

```php
$response = $anthropic->messages()->create(
    messages: [
        ['role' => 'user',      'content' => 'My name is Alice.'],
        ['role' => 'assistant', 'content' => 'Hello Alice, how can I help?'],
        ['role' => 'user',      'content' => 'What is my name?'],
    ],
);
```

### Streaming

```php
$stream = $anthropic->messages()->createStream(
    messages: [['role' => 'user', 'content' => 'Tell me a short story.']],
);

foreach ($stream as $event) {
    // handle streaming events
}
```

### Tool Use

```php
$tools = [
    [
        'name'         => 'get_weather',
        'description'  => 'Get the current weather for a location.',
        'input_schema' => [
            'type'       => 'object',
            'properties' => [
                'location' => ['type' => 'string', 'description' => 'City and country'],
            ],
            'required'   => ['location'],
        ],
    ],
];

$response = $anthropic->messages()->create(
    messages: [['role' => 'user', 'content' => 'What is the weather in Dubai?']],
    tools:    $tools,
);

foreach ($response->content as $block) {
    if ($block->type === 'tool_use') {
        $result = callTool($block->name, $block->input);

        // Send the tool result back
        $followup = $anthropic->messages()->create(
            messages: [
                ['role' => 'user', 'content' => 'What is the weather in Dubai?'],
                ['role' => 'assistant', 'content' => $response->content],
                ['role' => 'user', 'content' => [
                    ['type' => 'tool_result', 'tool_use_id' => $block->id, 'content' => $result],
                ]],
            ],
            tools: $tools,
        );
    }
}
```

### Raw SDK Access

For anything not covered by the driver wrapper, use `raw()` to access the full Anthropic SDK client directly:

```php
$client = $anthropic->raw();
```

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).
