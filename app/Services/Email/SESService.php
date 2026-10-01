<?php

namespace App\Services\Email;

use App\Services\Email\Contracts\EmailProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Amazon Simple Email Service (SES) v2 Outbound Provider.
 *
 * Implements Amazon SES v2 REST API with AWS Signature Version 4 (SigV4)
 * authorization, supporting HTML and plain-text multipart emails, tagging,
 * and sandbox/mock fallback for automated testing and local development.
 */
class SESService implements EmailProviderInterface
{
    protected string $accessKey;

    protected string $secretKey;

    protected string $region;

    protected string $defaultFromEmail;

    protected string $defaultFromName;

    protected bool $mockMode;

    public function __construct(
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $region = null,
        ?string $defaultFromEmail = null,
        ?string $defaultFromName = null,
        ?bool $mockMode = null
    ) {
        $this->accessKey = $accessKey ?? (string) config('services.ses.key', env('AWS_ACCESS_KEY_ID', ''));
        $this->secretKey = $secretKey ?? (string) config('services.ses.secret', env('AWS_SECRET_ACCESS_KEY', ''));
        $this->region = $region ?? (string) config('services.ses.region', env('AWS_SES_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')));
        $this->defaultFromEmail = $defaultFromEmail ?? (string) config('services.ses.from_address', env('AWS_SES_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@bamcomcrm.com')));
        $this->defaultFromName = $defaultFromName ?? (string) config('services.ses.from_name', env('AWS_SES_FROM_NAME', env('MAIL_FROM_NAME', 'Bamcom AI CRM')));

        $this->mockMode = $mockMode ?? (
            (bool) config('services.ses.mock', env('SES_SANDBOX_MOCK', false))
            || empty($this->accessKey)
            || empty($this->secretKey)
            || app()->environment('testing')
        );
    }

    /**
     * Send an email payload via Amazon SES v2.
     *
     * @param array{
     *     to: string|list<string>,
     *     to_name?: ?string,
     *     from_email?: ?string,
     *     from_name?: ?string,
     *     reply_to?: ?string,
     *     subject: string,
     *     html: string,
     *     plain?: ?string,
     *     metadata?: array<string, mixed>,
     *     tags?: array<string, string>,
     *     configuration_set?: ?string
     * } $payload
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     error: ?string,
     *     response: array<string, mixed>
     * }
     */
    public function send(array $payload): array
    {
        $toAddresses = is_array($payload['to']) ? array_values($payload['to']) : [trim($payload['to'])];
        $fromEmail = ! empty($payload['from_email']) ? trim($payload['from_email']) : $this->defaultFromEmail;
        $fromName = ! empty($payload['from_name']) ? trim($payload['from_name']) : $this->defaultFromName;
        $fromFormatted = ! empty($fromName) ? "{$fromName} <{$fromEmail}>" : $fromEmail;

        $subject = $payload['subject'];
        $html = $payload['html'];
        $plain = $payload['plain'] ?? strip_tags($html);
        $replyTo = ! empty($payload['reply_to']) ? [trim($payload['reply_to'])] : [];

        // Build Amazon SES v2 Request Body
        $body = [
            'FromEmailAddress' => $fromFormatted,
            'Destination' => [
                'ToAddresses' => $toAddresses,
            ],
            'Content' => [
                'Simple' => [
                    'Subject' => [
                        'Data' => $subject,
                        'Charset' => 'UTF-8',
                    ],
                    'Body' => [
                        'Html' => [
                            'Data' => $html,
                            'Charset' => 'UTF-8',
                        ],
                    ],
                ],
            ],
        ];

        if (! empty($plain)) {
            $body['Content']['Simple']['Body']['Text'] = [
                'Data' => $plain,
                'Charset' => 'UTF-8',
            ];
        }

        if (! empty($replyTo)) {
            $body['ReplyToAddresses'] = $replyTo;
        }

        if (! empty($payload['configuration_set'])) {
            $body['ConfigurationSetName'] = $payload['configuration_set'];
        }

        // Mock / Sandbox Mode for Testing & Local Dev
        if ($this->mockMode) {
            $mockMessageId = sprintf('ses-%s-%s', (string) Str::uuid(), time());
            Log::info('SESService [MOCK]: Dispatched email successfully', [
                'to' => $toAddresses,
                'from' => $fromFormatted,
                'subject' => $subject,
                'mock_message_id' => $mockMessageId,
            ]);

            return [
                'success' => true,
                'message_id' => $mockMessageId,
                'error' => null,
                'response' => [
                    'MessageId' => $mockMessageId,
                    'Mock' => true,
                    'Timestamp' => now()->toIso8601String(),
                ],
            ];
        }

        // Live Amazon SES v2 REST Call with SigV4
        try {
            $endpoint = "https://email.{$this->region}.amazonaws.com/v2/email/outbound-emails";
            $payloadJson = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $headers = $this->signRequest(
                method: 'POST',
                path: '/v2/email/outbound-emails',
                queryString: '',
                payload: $payloadJson
            );

            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->withBody($payloadJson, 'application/json')
                ->post($endpoint);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                $messageId = $data['MessageId'] ?? null;

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                    'response' => $data,
                ];
            }

            $errorMessage = $this->extractErrorMessage($response->json(), $response->status(), $response->body());
            Log::error('SESService [AWS ERROR]: Failed to send outbound email', [
                'status' => $response->status(),
                'error' => $errorMessage,
                'to' => $toAddresses,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'response' => $response->json() ?? ['raw' => $response->body()],
            ];
        } catch (Throwable $e) {
            Log::error('SESService [EXCEPTION]: Error executing SES request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'response' => ['exception' => get_class($e)],
            ];
        }
    }

    /**
     * Get sending quota metrics.
     *
     * @return array{
     *     max_24_hour_send: float,
     *     sent_last_24_hours: float,
     *     max_send_rate: float
     * }
     */
    public function getQuota(): array
    {
        if ($this->mockMode) {
            return [
                'max_24_hour_send' => 50000.0,
                'sent_last_24_hours' => 120.0,
                'max_send_rate' => 14.0,
            ];
        }

        try {
            $endpoint = "https://email.{$this->region}.amazonaws.com/v2/email/account";
            $headers = $this->signRequest(
                method: 'GET',
                path: '/v2/email/account',
                queryString: '',
                payload: ''
            );

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get($endpoint);

            if ($response->successful()) {
                $data = $response->json()['SendQuota'] ?? [];

                return [
                    'max_24_hour_send' => (float) ($data['Max24HourSend'] ?? 50000.0),
                    'sent_last_24_hours' => (float) ($data['SentLast24Hours'] ?? 0.0),
                    'max_send_rate' => (float) ($data['MaxSendRate'] ?? 14.0),
                ];
            }
        } catch (Throwable $e) {
            Log::warning('SESService: Could not retrieve account quota: '.$e->getMessage());
        }

        return [
            'max_24_hour_send' => 50000.0,
            'sent_last_24_hours' => 0.0,
            'max_send_rate' => 14.0,
        ];
    }

    /**
     * Get provider name.
     */
    public function getName(): string
    {
        return 'ses';
    }

    /**
     * Sign an HTTP request using AWS Signature Version 4 (SigV4).
     *
     * @return array<string, string>
     */
    protected function signRequest(string $method, string $path, string $queryString, string $payload): array
    {
        $service = 'ses';
        $host = "email.{$this->region}.amazonaws.com";
        $now = Carbon::now('UTC');
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');

        $payloadHash = hash('sha256', $payload);

        // Canonical Request
        $canonicalHeaders = "content-type:application/json\n"
            ."host:{$host}\n"
            ."x-amz-date:{$amzDate}\n";
        $signedHeaders = 'content-type;host;x-amz-date';

        $canonicalRequest = "{$method}\n"
            ."{$path}\n"
            ."{$queryString}\n"
            ."{$canonicalHeaders}\n"
            ."{$signedHeaders}\n"
            ."{$payloadHash}";

        // String to Sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "{$algorithm}\n"
            ."{$amzDate}\n"
            ."{$credentialScope}\n"
            .hash('sha256', $canonicalRequest);

        // Calculate Signature
        $kSecret = 'AWS4'.$this->secretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Content-Type' => 'application/json',
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'Authorization' => $authorizationHeader,
        ];
    }

    /**
     * Extract human-readable error description from AWS response.
     */
    protected function extractErrorMessage(?array $json, int $statusCode, string $rawBody): string
    {
        if (! empty($json['message'])) {
            return (string) $json['message'];
        }

        if (! empty($json['Message'])) {
            return (string) $json['Message'];
        }

        return "Amazon SES error (HTTP {$statusCode}): ".Str::limit($rawBody, 150);
    }
}
