<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: 'gemini', 'openai', 'mock'
    |
    */
    'default_provider' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'temperature' => (float) env('AI_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
            'timeout' => (int) env('AI_HTTP_TIMEOUT', 20),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'temperature' => (float) env('AI_TEMPERATURE', 0.2),
            'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
            'timeout' => (int) env('AI_HTTP_TIMEOUT', 20),
        ],

        'mock' => [
            'default_intent' => 'property_inquiry',
            'default_reply' => 'Thank you for your inquiry with Bamcom Properties. Our verified estate inventory has available units in Epe and Ibeju-Lekki.',
            'confidence' => 0.95,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Guardrails & Security Policies
    |--------------------------------------------------------------------------
    |
    | Strictly disallow models from directly issuing raw SQL or unvetted updates.
    | All database interactions must occur through whitelisted application tools.
    |
    */
    'guardrails' => [
        'disallow_raw_sql' => true,
        'disallow_direct_db_write' => true,
        'require_tool_validation' => true,
        'max_history_messages' => (int) env('AI_MAX_HISTORY_MESSAGES', 15),
        'system_identity' => 'Bamcom AI Real Estate Assistant',
        'enforce_ground_truth' => true,
    ],
];
