<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppClient $client
    ) {}

    /**
     * Handle Meta Webhook Verification Handshake (GET).
     */
    public function verify(Request $request): Response
    {
        $mode = $request->input('hub_mode', $request->input('hub.mode'));
        $token = $request->input('hub_verify_token', $request->input('hub.verify_token'));
        $challenge = $request->input('hub_challenge', $request->input('hub.challenge'));

        if ($mode === 'subscribe' && $this->client->verifyWebhookToken($token)) {
            Log::info('Meta WhatsApp webhook subscription verified successfully.');

            // Update account verification timestamp if exists
            WhatsAppAccount::default()->update(['webhook_verified_at' => now()]);

            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Meta WhatsApp webhook verification failed.', [
            'mode' => $mode,
            'token_provided' => ! empty($token),
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle Meta Incoming Webhook Notifications (POST).
     */
    public function receive(Request $request): JsonResponse
    {
        $signature = $request->header('X-Hub-Signature-256');
        $rawPayload = $request->getContent();

        $isValidSignature = empty($signature) || $this->client->verifyWebhookSignature($rawPayload, $signature);

        $payload = $request->all();

        // Extract event metadata
        $entry = $payload['entry'][0] ?? [];
        $changes = $entry['changes'][0] ?? [];
        $value = $changes['value'] ?? [];
        $field = $changes['field'] ?? 'messages';

        $phoneMetadata = $value['metadata'] ?? [];
        $displayPhoneNumber = $phoneMetadata['display_phone_number'] ?? null;
        $phoneNumberId = $phoneMetadata['phone_number_id'] ?? null;

        // Find associated account
        $account = $phoneNumberId ? WhatsAppAccount::where('phone_number_id', $phoneNumberId)->first() : null;

        $senderPhone = null;
        $metaEventId = null;

        if (! empty($value['messages'][0])) {
            $msg = $value['messages'][0];
            $senderPhone = $msg['from'] ?? null;
            $metaEventId = $msg['id'] ?? null;
        } elseif (! empty($value['statuses'][0])) {
            $statusItem = $value['statuses'][0];
            $senderPhone = $statusItem['recipient_id'] ?? null;
            $metaEventId = $statusItem['id'] ?? null;
        }

        // Store webhook event for audit trail
        WhatsAppWebhookEvent::create([
            'whatsapp_account_id' => $account?->id,
            'event_type' => $field,
            'meta_event_id' => $metaEventId,
            'sender_phone' => $senderPhone,
            'recipient_phone' => $displayPhoneNumber,
            'payload' => $payload,
            'headers' => [
                'x-hub-signature-256' => $signature,
                'user-agent' => $request->userAgent(),
            ],
            'signature' => $signature,
            'status' => $isValidSignature ? 'pending' : 'failed',
            'error_message' => $isValidSignature ? null : 'Invalid HMAC-SHA256 signature',
        ]);

        return response()->json(['status' => 'received'], 200);
    }
}
