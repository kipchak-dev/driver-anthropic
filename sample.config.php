<?php

use function Kipchak\Core\env;

return [
    'connections' => [
        'default' => [
            'api_key'       => env('ANTHROPIC_API_KEY', ''),
            'default_model' => env('ANTHROPIC_DEFAULT_MODEL', 'claude-sonnet-4-6'),
            // 'max_retries' => 2,
        ],
    ],
];
