<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Meta WhatsApp Business Cloud API Version
    |--------------------------------------------------------------------------
    |
    | The official Graph API version targeted by this application.
    | Defaults to v21.0.
    |
    */
    'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),

    /*
    |--------------------------------------------------------------------------
    | Meta Graph API Base URL
    |--------------------------------------------------------------------------
    |
    | The base endpoint for Meta's Graph API.
    |
    */
    'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),

    /*
    |--------------------------------------------------------------------------
    | Permanent or System User Access Token
    |--------------------------------------------------------------------------
    |
    | Permanent token generated from Meta Business Manager with permissions:
    | whatsapp_business_messaging, whatsapp_business_management.
    |
    */
    'access_token' => env('WHATSAPP_ACCESS_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Default WhatsApp Phone Number ID
    |--------------------------------------------------------------------------
    |
    | The ID assigned by Meta to the sending WhatsApp Business phone number.
    |
    */
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Account ID (WABA ID)
    |--------------------------------------------------------------------------
    |
    | The unique identifier for the Meta WhatsApp Business Account (WABA).
    | Required for managing templates and phone number directories.
    |
    */
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Meta App Credentials
    |--------------------------------------------------------------------------
    |
    | App ID and App Secret used to compute and verify HMAC-SHA256 signatures
    | on incoming webhooks from Meta.
    |
    */
    'app_id' => env('WHATSAPP_APP_ID', ''),
    'app_secret' => env('WHATSAPP_APP_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Webhook Verification Token & Signature Secret
    |--------------------------------------------------------------------------
    |
    | The verification token configured in the Meta App Dashboard when setting
    | up the webhook subscription.
    |
    */
    'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Client HTTP Timeout & Retries
    |--------------------------------------------------------------------------
    |
    | Default timeout in seconds for outgoing HTTP requests to the Meta API,
    | plus retry configuration for transient network or rate-limiting glitches.
    |
    */
    'timeout' => (int) env('WHATSAPP_HTTP_TIMEOUT', 15),
    'retry' => [
        'times' => (int) env('WHATSAPP_RETRY_TIMES', 3),
        'sleep_ms' => (int) env('WHATSAPP_RETRY_SLEEP_MS', 500),
    ],
];
