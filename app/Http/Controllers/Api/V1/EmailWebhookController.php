<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmailMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Services\Email\EmailSuppressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Amazon Simple Notification Service (SNS) & SES Webhook Handler.
 *
 * Ingests real-time Amazon SES delivery, bounce, complaint, open, and click events,
 * updating message statuses and synchronizing suppressions with authoritative contacts.
 */
class EmailWebhookController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService
    ) {}

    /**
     * Ingest and process SES webhook payload.
     */
    public function handle(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        $payload = json_decode($rawContent, true);

        if (! is_array($payload)) {
            $payload = $request->all();
        }

        // 1. Handle AWS SNS Subscription Confirmation
        if (($payload['Type'] ?? null) === 'SubscriptionConfirmation' && ! empty($payload['SubscribeURL'])) {
            Log::info('EmailWebhookController: Confirming AWS SNS subscription', ['topic' => $payload['TopicArn'] ?? null]);
            Http::timeout(10)->get($payload['SubscribeURL']);

            return response()->json(['message' => 'Subscription confirmed successfully.']);
        }

        // 2. Parse SNS Message Wrapper if present
        $messageData = $payload;
        if (($payload['Type'] ?? null) === 'Notification' && isset($payload['Message'])) {
            $decoded = json_decode((string) $payload['Message'], true);
            if (is_array($decoded)) {
                $messageData = $decoded;
            }
        }

        $eventType = $messageData['eventType'] ?? $messageData['notificationType'] ?? null;
        $mail = $messageData['mail'] ?? [];
        $providerMessageId = $mail['messageId'] ?? null;

        Log::info('EmailWebhookController: Received SES webhook event', [
            'event' => $eventType,
            'message_id' => $providerMessageId,
        ]);

        $emailMessage = null;
        if ($providerMessageId) {
            $emailMessage = EmailMessage::where('provider_message_id', $providerMessageId)->first();
        }

        // Fallback: match by destination recipient email if message ID not matched
        if (! $emailMessage && ! empty($mail['destination'][0])) {
            $emailMessage = EmailMessage::where('to_email', strtolower($mail['destination'][0]))
                ->latest('id')
                ->first();
        }

        $timestamp = isset($mail['timestamp']) ? Carbon::parse($mail['timestamp']) : Carbon::now();

        switch (strtolower((string) $eventType)) {
            case 'bounce':
                $bounce = $messageData['bounce'] ?? [];
                $bounceType = $bounce['bounceType'] ?? 'Permanent';
                $recipients = $bounce['bouncedRecipients'] ?? [];

                foreach ($recipients as $recipient) {
                    $bouncedEmail = $recipient['emailAddress'] ?? null;
                    $diagnostic = $recipient['diagnosticCode'] ?? null;

                    if ($bouncedEmail) {
                        $this->suppressionService->recordBounce(
                            email: $bouncedEmail,
                            bounceType: $bounceType,
                            diagnostic: $diagnostic
                        );
                    }
                }

                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Bounced,
                        'bounced_at' => $timestamp,
                        'error_message' => 'SES Bounce: '.($bounce['bounceSubType'] ?? $bounceType),
                    ]);

                    EmailEvent::create([
                        'email_message_id' => $emailMessage->id,
                        'event_type' => 'bounce',
                        'provider_event_id' => $bounce['feedbackId'] ?? null,
                        'payload' => $bounce,
                        'occurred_at' => $timestamp,
                    ]);
                }
                break;

            case 'complaint':
                $complaint = $messageData['complaint'] ?? [];
                $feedbackType = $complaint['complaintFeedbackType'] ?? 'abuse';
                $recipients = $complaint['complainedRecipients'] ?? [];

                foreach ($recipients as $recipient) {
                    $complaintEmail = $recipient['emailAddress'] ?? null;
                    if ($complaintEmail) {
                        $this->suppressionService->recordComplaint(
                            email: $complaintEmail,
                            feedbackType: $feedbackType
                        );
                    }
                }

                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Complained,
                        'complained_at' => $timestamp,
                        'error_message' => 'SES Complaint: '.$feedbackType,
                    ]);

                    EmailEvent::create([
                        'email_message_id' => $emailMessage->id,
                        'event_type' => 'complaint',
                        'provider_event_id' => $complaint['feedbackId'] ?? null,
                        'payload' => $complaint,
                        'occurred_at' => $timestamp,
                    ]);
                }
                break;

            case 'delivery':
                $delivery = $messageData['delivery'] ?? [];
                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Delivered,
                        'delivered_at' => $timestamp,
                    ]);

                    EmailEvent::create([
                        'email_message_id' => $emailMessage->id,
                        'event_type' => 'delivery',
                        'provider_event_id' => $delivery['smtpResponse'] ?? null,
                        'payload' => $delivery,
                        'occurred_at' => $timestamp,
                    ]);
                }
                break;

            case 'open':
                $open = $messageData['open'] ?? [];
                if ($emailMessage) {
                    $emailMessage->update([
                        'opened_at' => $emailMessage->opened_at ?? $timestamp,
                    ]);

                    EmailEvent::create([
                        'email_message_id' => $emailMessage->id,
                        'event_type' => 'open',
                        'ip_address' => $open['ipAddress'] ?? $request->ip(),
                        'user_agent' => $open['userAgent'] ?? $request->userAgent(),
                        'payload' => $open,
                        'occurred_at' => $timestamp,
                    ]);
                }
                break;

            case 'click':
                $click = $messageData['click'] ?? [];
                if ($emailMessage) {
                    $emailMessage->update([
                        'clicked_at' => $emailMessage->clicked_at ?? $timestamp,
                    ]);

                    EmailEvent::create([
                        'email_message_id' => $emailMessage->id,
                        'event_type' => 'click',
                        'ip_address' => $click['ipAddress'] ?? $request->ip(),
                        'user_agent' => $click['userAgent'] ?? $request->userAgent(),
                        'payload' => $click,
                        'occurred_at' => $timestamp,
                    ]);
                }
                break;

            default:
                Log::info("EmailWebhookController: Handled event {$eventType}");
                break;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event processed successfully.',
            'event' => $eventType,
        ]);
    }
}
