<?php

namespace App\Services\WhatsApp;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppClient
{
    protected string $baseUrl;

    protected string $version;

    protected string $accessToken;

    protected string $phoneNumberId;

    protected string $businessAccountId;

    protected string $appSecret;

    protected string $verifyToken;

    protected int $timeout;

    /**
     * @var array{times: int, sleep_ms: int}
     */
    protected array $retry;

    public function __construct()
    {
        $this->baseUrl = (string) config('whatsapp.base_url', 'https://graph.facebook.com');
        $this->version = (string) config('whatsapp.api_version', 'v21.0');
        $this->accessToken = (string) config('whatsapp.access_token', '');
        $this->phoneNumberId = (string) config('whatsapp.phone_number_id', '');
        $this->businessAccountId = (string) config('whatsapp.business_account_id', '');
        $this->appSecret = (string) config('whatsapp.app_secret', '');
        $this->verifyToken = (string) config('whatsapp.verify_token', '');
        $this->timeout = (int) config('whatsapp.timeout', 15);
        $this->retry = (array) config('whatsapp.retry', ['times' => 3, 'sleep_ms' => 500]);
    }

    /**
     * Check if minimum credentials (access token and phone number ID) are set in environment.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->accessToken) && ! empty($this->phoneNumberId);
    }

    /**
     * Get default phone number ID.
     */
    public function getPhoneNumberId(): string
    {
        return $this->phoneNumberId;
    }

    /**
     * Get default WhatsApp Business Account ID.
     */
    public function getBusinessAccountId(): string
    {
        return $this->businessAccountId;
    }

    /**
     * Get API Version.
     */
    public function getApiVersion(): string
    {
        return $this->version;
    }

    /**
     * Base HTTP request builder with authorization and retries.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl("{$this->baseUrl}/{$this->version}")
            ->withToken($this->accessToken)
            ->timeout($this->timeout)
            ->retry(
                $this->retry['times'] ?? 3,
                $this->retry['sleep_ms'] ?? 500,
                fn (Exception $exception): bool => ! ($exception instanceof RequestException && $exception->response->status() === 401)
            )
            ->acceptJson();
    }

    /**
     * Send a single text message to a WhatsApp recipient.
     *
     * @param  string  $to  Recipient phone number in international format without leading '+'
     * @param  string  $body  Message content text
     * @param  bool  $previewUrl  Whether URL previews should be generated
     * @param  ?string  $phoneNumberId  Optional override for sending line
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function sendTextMessage(string $to, string $body, bool $previewUrl = false, ?string $phoneNumberId = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $body,
            ],
        ];

        $response = $this->request()->post("/{$phoneId}/messages", $payload);

        return $this->handleResponse($response, 'sendTextMessage');
    }

    /**
     * Send a pre-approved template message to a WhatsApp recipient.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $templateName  Meta template name (e.g. 'inspection_booking_confirmation')
     * @param  string  $languageCode  Language code (e.g. 'en_US')
     * @param  array<int, mixed>  $components  Template component parameter payload
     * @param  ?string  $phoneNumberId  Optional override for sending line
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
        ?string $phoneNumberId = null
    ): array {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if (! empty($components)) {
            $payload['template']['components'] = $components;
        }

        $response = $this->request()->post("/{$phoneId}/messages", $payload);

        return $this->handleResponse($response, 'sendTemplateMessage');
    }

    /**
     * Send an image or document media message.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $type  Media type: 'image' or 'document'
     * @param  string  $mediaUrl  Public URL of the media file
     * @param  ?string  $caption  Optional caption text
     * @param  ?string  $phoneNumberId  Optional override for sending line
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function sendMediaMessage(
        string $to,
        string $type,
        string $mediaUrl,
        ?string $caption = null,
        ?string $phoneNumberId = null
    ): array {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;

        $mediaPayload = [
            'link' => $mediaUrl,
        ];

        if ($caption !== null) {
            $mediaPayload['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $mediaPayload,
        ];

        $response = $this->request()->post("/{$phoneId}/messages", $payload);

        return $this->handleResponse($response, 'sendMediaMessage');
    }

    /**
     * Fetch message templates registered under the WhatsApp Business Account.
     *
     * @param  ?string  $wabaId  Optional WABA ID override
     * @param  int  $limit  Max templates to retrieve (default: 100)
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function fetchTemplates(?string $wabaId = null, int $limit = 100): array
    {
        $account = $wabaId ?: $this->businessAccountId;

        $response = $this->request()->get("/{$account}/message_templates", [
            'limit' => $limit,
        ]);

        return $this->handleResponse($response, 'fetchTemplates');
    }

    /**
     * Query phone number specifications (name, quality rating, verification status).
     *
     * @param  ?string  $phoneNumberId  Optional phone ID override
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function getPhoneNumberDetails(?string $phoneNumberId = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;

        $response = $this->request()->get("/{$phoneId}", [
            'fields' => 'verified_name,code_verification_status,display_phone_number,quality_rating',
        ]);

        return $this->handleResponse($response, 'getPhoneNumberDetails');
    }

    /**
     * Test connection to Meta Graph API by pinging phone number details.
     *
     * @return array{success: bool, status: string, message: string, data?: array<string, mixed>, error?: string}
     */
    public function testConnection(?string $phoneNumberId = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'message' => 'WhatsApp API credentials (WHATSAPP_ACCESS_TOKEN and WHATSAPP_PHONE_NUMBER_ID) are not configured.',
            ];
        }

        try {
            $data = $this->getPhoneNumberDetails($phoneNumberId);

            return [
                'success' => true,
                'status' => 'connected',
                'message' => 'Successfully connected to Meta WhatsApp Business Cloud API.',
                'data' => $data,
            ];
        } catch (Exception $e) {
            Log::warning('WhatsApp connection test failed: '.$e->getMessage());

            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'Connection test failed: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Meta Webhook Signature (HMAC-SHA256).
     *
     * @param  string  $rawPayload  The raw JSON request body
     * @param  ?string  $signatureHeader  The 'X-Hub-Signature-256' header value
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (empty($this->appSecret) || empty($signatureHeader)) {
            return false;
        }

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expectedHash = hash_hmac('sha256', $rawPayload, $this->appSecret);
        $providedHash = substr($signatureHeader, 7);

        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Validate incoming webhook verification token.
     */
    public function verifyWebhookToken(?string $token): bool
    {
        if (empty($this->verifyToken) || empty($token)) {
            return false;
        }

        return hash_equals($this->verifyToken, $token);
    }

    /**
     * Handle Meta API Response or throw descriptive exception.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    protected function handleResponse(Response $response, string $operation): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        $errorData = $response->json('error') ?? [];
        $errorMessage = $errorData['message'] ?? $response->body();
        $errorCode = $errorData['code'] ?? $response->status();

        Log::error("WhatsApp API {$operation} failed [{$errorCode}]: {$errorMessage}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new Exception("Meta WhatsApp API error [{$errorCode}]: {$errorMessage}", (int) $errorCode);
    }
}
