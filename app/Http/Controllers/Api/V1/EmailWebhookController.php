<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmailCampaignRecipientStatus;
use App\Enums\EmailMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignMessage;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailEvent;
use App\Models\EmailMessage;
use App\Services\Activity\ActivityRecorderService;
use App\Services\Email\EmailSuppressionService;
use App\Services\Sequence\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Amazon Simple Notification Service (SNS) & SES Webhook Handler.
 *
 * Ingests real-time Amazon SES events:
 * sent, delivered, opened, clicked, bounced, complained, unsubscribed, failed.
 *
 * Guarantees idempotency via provider event IDs, updates message & campaign recipient statuses,
 * synchronizes contact suppression states, halts active sequences on terminal failures,
 * and records email engagement on the CRM activity timeline.
 */
class EmailWebhookController extends Controller
{
    public function __construct(
        protected EmailSuppressionService $suppressionService,
        protected ActivityRecorderService $activityRecorder,
        protected SequenceService $sequenceService
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

        $rawEventType = $messageData['eventType'] ?? $messageData['notificationType'] ?? $messageData['event'] ?? 'unknown';
        $normalizedType = $this->normalizeEventType($rawEventType);
        $mail = $messageData['mail'] ?? [];
        $providerMessageId = $mail['messageId'] ?? null;
        $timestamp = isset($mail['timestamp']) ? Carbon::parse($mail['timestamp']) : Carbon::now();

        Log::info('EmailWebhookController: Received SES webhook event', [
            'raw_event' => $rawEventType,
            'normalized' => $normalizedType,
            'message_id' => $providerMessageId,
        ]);

        // 3. Resolve Provider Event ID & Check for Duplicate Processing (Idempotency)
        $providerEventId = $this->resolveProviderEventId($normalizedType, $messageData, $mail, $timestamp);

        if ($providerEventId && EmailEvent::where('provider_event_id', $providerEventId)->exists()) {
            Log::info('EmailWebhookController: Duplicate SES event ignored', [
                'event' => $normalizedType,
                'provider_event_id' => $providerEventId,
            ]);

            return response()->json([
                'status' => 'duplicate',
                'message' => 'Event already processed.',
                'event' => $normalizedType,
                'provider_event_id' => $providerEventId,
            ], 200);
        }

        // 4. Resolve EmailMessage Model
        $emailMessage = null;
        if ($providerMessageId) {
            $emailMessage = EmailMessage::where('provider_message_id', $providerMessageId)->first();
        }

        if (! $emailMessage && ! empty($mail['destination'][0])) {
            $emailMessage = EmailMessage::where('to_email', strtolower($mail['destination'][0]))
                ->latest('id')
                ->first();
        }

        if (! $providerEventId && $emailMessage) {
            $existing = EmailEvent::where('email_message_id', $emailMessage->id)
                ->where('event_type', $normalizedType)
                ->where('occurred_at', $timestamp)
                ->exists();

            if ($existing) {
                return response()->json([
                    'status' => 'duplicate',
                    'message' => 'Event already processed.',
                    'event' => $normalizedType,
                ], 200);
            }
        }

        // 5. Resolve Associated Campaign, Recipient & Contact
        $campaign = null;
        $recipient = null;
        $contact = $emailMessage?->contact;

        if ($emailMessage) {
            $recipient = EmailCampaignRecipient::where('email_message_id', $emailMessage->id)->first();

            if (! $recipient && ! empty($emailMessage->metadata['recipient_id'])) {
                $recipient = EmailCampaignRecipient::find($emailMessage->metadata['recipient_id']);
            }

            if (! $recipient) {
                $campaignMessage = EmailCampaignMessage::where('email_message_id', $emailMessage->id)->first();
                if ($campaignMessage) {
                    $recipient = $campaignMessage->recipient;
                    $campaign = $campaignMessage->campaign;
                }
            }

            if ($recipient && ! $campaign) {
                $campaign = $recipient->campaign;
            }

            if (! $campaign && ! empty($emailMessage->metadata['campaign_id'])) {
                $campaign = EmailCampaign::find($emailMessage->metadata['campaign_id']);
            }
        }

        if (! $contact && ! empty($mail['destination'][0])) {
            $contact = Contact::whereRaw('LOWER(email) = ?', [strtolower($mail['destination'][0])])->first();
        }

        // 6. Process Specific Event Logic
        $ipAddress = $messageData['open']['ipAddress'] ?? $messageData['click']['ipAddress'] ?? $request->ip();
        $userAgent = $messageData['open']['userAgent'] ?? $messageData['click']['userAgent'] ?? $request->userAgent();
        $linkUrl = $messageData['click']['link'] ?? null;
        $bounceType = null;
        $bounceSubType = null;
        $eventPayload = $messageData;

        switch ($normalizedType) {
            case 'sent':
                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Sent,
                        'sent_at' => $emailMessage->sent_at ?? $timestamp,
                    ]);
                }

                if ($recipient && $recipient->sent_at === null) {
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Sent,
                        'sent_at' => $timestamp,
                    ]);
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_sent',
                        description: "Email sent: \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'subject' => $emailMessage->subject,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'delivered':
                $delivery = $messageData['delivery'] ?? [];
                $eventPayload = $delivery;

                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Delivered,
                        'delivered_at' => $timestamp,
                    ]);
                }

                if ($recipient && $recipient->delivered_at === null) {
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Delivered,
                        'delivered_at' => $timestamp,
                    ]);
                    $campaign?->increment('delivered_count');
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_delivered',
                        description: "Email delivered successfully: \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'smtp_response' => $delivery['smtpResponse'] ?? null,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'opened':
                $open = $messageData['open'] ?? [];
                $eventPayload = $open;

                if ($emailMessage) {
                    $emailMessage->update([
                        'opened_at' => $emailMessage->opened_at ?? $timestamp,
                    ]);
                }

                if ($recipient) {
                    if ($recipient->opened_at === null) {
                        $campaign?->increment('opened_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Opened,
                        'opened_at' => $recipient->opened_at ?? $timestamp,
                    ]);
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_opened',
                        description: "Email opened: \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'ip_address' => $ipAddress,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'clicked':
                $click = $messageData['click'] ?? [];
                $eventPayload = $click;
                $linkUrl = $click['link'] ?? null;

                if ($emailMessage) {
                    $emailMessage->update([
                        'clicked_at' => $emailMessage->clicked_at ?? $timestamp,
                    ]);
                }

                if ($recipient) {
                    if ($recipient->clicked_at === null) {
                        $campaign?->increment('clicked_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Clicked,
                        'clicked_at' => $recipient->clicked_at ?? $timestamp,
                    ]);
                }

                if ($contact && $emailMessage) {
                    $linkDetail = $linkUrl ? " ({$linkUrl})" : '';
                    $this->activityRecorder->record(
                        type: 'email_clicked',
                        description: "Email link clicked: \"{$emailMessage->subject}\"{$linkDetail}",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'link' => $linkUrl,
                            'ip_address' => $ipAddress,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'bounced':
                $bounce = $messageData['bounce'] ?? [];
                $eventPayload = $bounce;
                $bounceType = $bounce['bounceType'] ?? 'Permanent';
                $bounceSubType = $bounce['bounceSubType'] ?? null;
                $diagnostic = null;

                $recipients = $bounce['bouncedRecipients'] ?? [];
                foreach ($recipients as $item) {
                    $bouncedEmail = $item['emailAddress'] ?? null;
                    $diagnostic = $item['diagnosticCode'] ?? null;

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
                        'error_message' => 'SES Bounce ('.$bounceType.'): '.($diagnostic ?? $bounceSubType),
                    ]);
                }

                if ($recipient) {
                    if ($recipient->bounced_at === null) {
                        $campaign?->increment('bounced_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Bounced,
                        'bounced_at' => $timestamp,
                        'error_message' => 'SES Bounce ('.$bounceType.'): '.($diagnostic ?? $bounceSubType),
                    ]);
                }

                // If permanent hard bounce, halt active follow-up sequences for contact
                if (strcasecmp($bounceType, 'Permanent') === 0 && $contact) {
                    $this->sequenceService->unenrollContact(
                        $contact,
                        null,
                        'Cancelled due to permanent email bounce: '.($diagnostic ?? $bounceSubType ?? 'Unknown bounce')
                    );
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_bounced',
                        description: "Email bounced ({$bounceType}): \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'bounce_type' => $bounceType,
                            'bounce_subtype' => $bounceSubType,
                            'diagnostic' => $diagnostic,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'complained':
                $complaint = $messageData['complaint'] ?? [];
                $eventPayload = $complaint;
                $feedbackType = $complaint['complaintFeedbackType'] ?? 'abuse';

                $recipients = $complaint['complainedRecipients'] ?? [];
                foreach ($recipients as $item) {
                    $complaintEmail = $item['emailAddress'] ?? null;
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
                }

                if ($recipient) {
                    if ($recipient->complained_at === null) {
                        $campaign?->increment('complained_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Complained,
                        'complained_at' => $timestamp,
                        'error_message' => 'SES Complaint: '.$feedbackType,
                    ]);
                }

                // Halts all active follow-up sequences for contact on spam complaint
                if ($contact) {
                    $this->sequenceService->unenrollContact(
                        $contact,
                        null,
                        'Cancelled due to recipient spam complaint: '.$feedbackType
                    );
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_complaint',
                        description: "Email marked as spam: \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'feedback_type' => $feedbackType,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'unsubscribed':
                $email = $mail['destination'][0] ?? $emailMessage?->to_email;
                if ($email) {
                    $this->suppressionService->recordUnsubscribe($email, 'Unsubscribed via email event');
                }

                if ($recipient) {
                    if ($recipient->unsubscribed_at === null) {
                        $campaign?->increment('unsubscribed_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Unsubscribed,
                        'unsubscribed_at' => $timestamp,
                    ]);
                }

                // Stop active sequences for contact
                if ($contact) {
                    $this->sequenceService->unenrollContact(
                        $contact,
                        null,
                        'Cancelled due to email unsubscribe'
                    );
                }

                if ($contact) {
                    $this->activityRecorder->record(
                        type: 'email_unsubscribed',
                        description: 'Contact unsubscribed from email marketing',
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage?->id,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            case 'failed':
                $failure = $messageData['failure'] ?? $messageData['reject'] ?? [];
                $errorMsg = $failure['errorMessage'] ?? $failure['reason'] ?? 'Email delivery rejected or failed rendering';
                $eventPayload = $failure;

                if ($emailMessage) {
                    $emailMessage->update([
                        'status' => EmailMessageStatus::Failed,
                        'error_message' => $errorMsg,
                    ]);
                }

                if ($recipient) {
                    if ($recipient->failed_at === null) {
                        $campaign?->increment('failed_count');
                    }
                    $recipient->update([
                        'status' => EmailCampaignRecipientStatus::Failed,
                        'failed_at' => $timestamp,
                        'error_message' => $errorMsg,
                    ]);
                }

                if ($contact && $emailMessage) {
                    $this->activityRecorder->record(
                        type: 'email_failed',
                        description: "Email delivery failed for \"{$emailMessage->subject}\"",
                        contact: $contact,
                        properties: [
                            'email_message_id' => $emailMessage->id,
                            'error_message' => $errorMsg,
                            'campaign_id' => $campaign?->id,
                        ]
                    );
                }
                break;

            default:
                Log::info("EmailWebhookController: Unhandled event type {$rawEventType}");
                break;
        }

        // 7. Record Immutable EmailEvent
        if ($emailMessage) {
            EmailEvent::create([
                'email_message_id' => $emailMessage->id,
                'contact_id' => $contact?->id,
                'email_campaign_id' => $campaign?->id,
                'event_type' => $normalizedType,
                'provider_event_id' => $providerEventId,
                'payload' => $eventPayload,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'link_url' => $linkUrl,
                'bounce_type' => $bounceType,
                'bounce_subtype' => $bounceSubType,
                'occurred_at' => $timestamp,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Event processed successfully.',
            'event' => $normalizedType,
            'provider_event_id' => $providerEventId,
        ]);
    }

    /**
     * Map arbitrary or SES event names to normalized system event types.
     */
    protected function normalizeEventType(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            'send', 'sent' => 'sent',
            'delivery', 'delivered' => 'delivered',
            'open', 'opened' => 'opened',
            'click', 'clicked' => 'clicked',
            'bounce', 'bounced' => 'bounced',
            'complaint', 'complained' => 'complained',
            'unsubscribe', 'unsubscribed', 'subscription' => 'unsubscribed',
            'reject', 'rendering failure', 'rendering_failure', 'failed', 'failure' => 'failed',
            default => strtolower(trim($eventType)),
        };
    }

    /**
     * Extract or generate a deterministic provider event ID for idempotency verification.
     *
     * @param  array<string, mixed>  $messageData
     * @param  array<string, mixed>  $mail
     */
    protected function resolveProviderEventId(
        string $normalizedType,
        array $messageData,
        array $mail,
        Carbon $timestamp
    ): ?string {
        if (! empty($messageData['eventId'])) {
            return (string) $messageData['eventId'];
        }

        $mailId = $mail['messageId'] ?? null;

        return match ($normalizedType) {
            'bounced' => $messageData['bounce']['feedbackId'] ?? ($mailId ? "{$mailId}-bounce" : null),
            'complained' => $messageData['complaint']['feedbackId'] ?? ($mailId ? "{$mailId}-complaint" : null),
            'delivered' => ! empty($messageData['delivery']['smtpResponse'])
                ? md5($mailId.':'.$messageData['delivery']['smtpResponse'])
                : ($mailId ? "{$mailId}-delivered" : null),
            'sent' => $mailId ? "{$mailId}-sent" : null,
            'opened' => $mailId ? "{$mailId}-open-".($messageData['open']['timestamp'] ?? $timestamp->timestamp) : null,
            'clicked' => $mailId ? "{$mailId}-click-".md5(($messageData['click']['link'] ?? '').($messageData['click']['timestamp'] ?? $timestamp->timestamp)) : null,
            'unsubscribed' => $mailId ? "{$mailId}-unsubscribed" : null,
            'failed' => $mailId ? "{$mailId}-failed" : null,
            default => $mailId ? "{$mailId}-{$normalizedType}-{$timestamp->timestamp}" : null,
        };
    }
}
