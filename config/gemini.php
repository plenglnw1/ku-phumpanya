<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('GEMINI_ENABLED', true),
    'api_key' => env('GEMINI_API_KEY', ''),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    'models' => [
        'router' => env('GEMINI_MODEL_ROUTER', 'gemini-2.5-flash-lite'),
        'sub' => env('GEMINI_MODEL_SUB', 'gemini-2.5-flash-lite'),
        'synth' => env('GEMINI_MODEL_SYNTH', 'gemini-2.5-flash'),
    ],

    'timeout' => (int) env('GEMINI_TIMEOUT', 20),
    'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 2048),
    'temperature' => (float) env('GEMINI_TEMPERATURE', 0.3),
    'force_tier' => env('GEMINI_FORCE_TIER', ''),

    // 0 turns 2.5 "thinking" off: it multiplies latency and its tokens count against
    // max_output_tokens. Empty leaves the model default.
    'thinking_budget' => env('GEMINI_THINKING_BUDGET', 0),
];
