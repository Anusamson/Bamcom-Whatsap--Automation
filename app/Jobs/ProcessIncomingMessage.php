<?php

namespace App\Jobs;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Enums\MessageDeliveryStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\Contact\PhoneNormalizerService;
use App\Services\Conversation\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessIncomingMessage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $messageData
     * @param  array<string, mixed>  $contactData
     */
    public function __construct(
        public WhatsAppWebhookEvent $event,
        public array $messageData,
        public array $contactData = [],
        public ?string $displayPhoneNumber = null,
        public ?string $phoneNumberId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $metaMessageId = $this->messageData['id'] ?? null;
        if (empty($metaMessageId)) {
            return;
        }

        // 1. Fast idempotency check - prevent duplicate message processing
        if (WhatsAppMessage::where('meta_message_id', $metaMessageId)->exists()) {
            Log::info("WhatsApp message already processed, skipping duplicate: {$metaMessageId}");

            return;
        }

        // 2. Concurrency guard using atomic lock
        $lockKey = 'whatsapp:dedup:'.$metaMessageId;
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            Log::info("WhatsApp message lock active, skipping concurrent duplicate: {$metaMessageId}");

            return;
        }

        try {
            // Re-check after obtaining lock
            if (WhatsAppMessage::where('meta_message_id', $metaMessageId)->exists()) {
                Log::info("WhatsApp message processed concurrently, skipping: {$metaMessageId}");

                return;
            }

            // 3. Resolve sender phone number
            $rawSenderPhone = (string) ($this->messageData['from'] ?? '');
            if ($rawSenderPhone === '') {
                Log::warning("Incoming WhatsApp message missing sender phone: {$metaMessageId}");

                return;
            }

            // 4. Resolve or auto-create CRM Contact
            $contact = $this->resolveOrCreateContact($rawSenderPhone);

            // 5. Extract message content and media metadata
            $extracted = $this->extractMessageContent();

            // 6. Resolve WhatsApp account
            $accountId = $this->resolveAccountId();

            // 7. Resolve or open active CRM conversation for contact
            /** @var ConversationService $conversationService */
            $conversationService = app(ConversationService::class);
            $conversation = $conversationService->findOrCreateActiveConversation($contact, $accountId);

            $sentAt = ! empty($this->messageData['timestamp'])
                ? Carbon::createFromTimestamp($this->messageData['timestamp'])
                : now();

            $recipientPhone = $this->displayPhoneNumber
                ?: ($this->event->recipient_phone ?: '');

            // 8. Persist authoritative CRM message
            $crmMessage = Message::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'sender_type' => 'contact',
                'sender_id' => null,
                'meta_message_id' => $metaMessageId,
                'direction' => 'inbound',
                'sender_phone' => $rawSenderPhone,
                'recipient_phone' => $recipientPhone,
                'type' => $extracted['type'],
                'body' => $extracted['body'],
                'media_url' => $extracted['media_url'],
                'media_mime_type' => $extracted['media_mime_type'],
                'media_metadata' => $extracted['media_metadata'],
                'delivery_status' => MessageDeliveryStatus::Received,
                'is_read' => false,
                'sent_at' => $sentAt,
                'payload' => $this->messageData,
            ]);

            // Bump conversation touchpoint & unread count
            $conversation->incrementUnread();
            $conversation->update(['last_message_at' => $sentAt]);

            // 9. Persist WhatsApp line message record
            $message = WhatsAppMessage::create([
                'whatsapp_account_id' => $accountId,
                'contact_id' => $contact->id,
                'meta_message_id' => $metaMessageId,
                'direction' => 'inbound',
                'sender_phone' => $rawSenderPhone,
                'recipient_phone' => $recipientPhone,
                'message_type' => $extracted['type'],
                'body' => $extracted['body'],
                'media_url' => $extracted['media_url'],
                'media_mime_type' => $extracted['media_mime_type'],
                'status' => 'received',
                'payload' => $this->messageData,
                'sent_at' => $sentAt,
            ]);

            // 10. Record audit activity on contact
            Activity::create([
                'user_id' => null,
                'activity_type' => 'whatsapp_message_received',
                'description' => $extracted['body'] ?: "Received {$extracted['type']} message from {$contact->full_name}",
                'properties' => [
                    'contact_id' => $contact->id,
                    'contact_name' => $contact->full_name,
                    'phone' => $contact->phone,
                    'meta_message_id' => $metaMessageId,
                    'message_type' => $extracted['type'],
                    'whatsapp_message_id' => $message->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $crmMessage->id,
                ],
            ]);

            // 11. Dispatch AI Sales Agent conversation pipeline
            ProcessConversationAiTurn::dispatch($conversation, $crmMessage);
        } catch (Throwable $e) {
            Log::error("Failed to process incoming WhatsApp message [{$metaMessageId}]: {$e->getMessage()}", [
                'event_id' => $this->event->id,
                'meta_message_id' => $metaMessageId,
                'exception' => $e,
            ]);

            $this->event->markFailed($e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Resolve existing contact or auto-register a new WhatsApp contact.
     */
    protected function resolveOrCreateContact(string $rawPhone): Contact
    {
        /** @var PhoneNormalizerService $normalizer */
        $normalizer = app(PhoneNormalizerService::class);
        $e164Phone = $normalizer->normalize($rawPhone);
        $digitsOnly = preg_replace('/\D/', '', $rawPhone) ?? '';
        $localDigits = strlen($digitsOnly) >= 10 ? substr($digitsOnly, -10) : $digitsOnly;

        $contact = Contact::where('phone', $rawPhone)
            ->orWhere('phone', $e164Phone)
            ->orWhere('phone', $digitsOnly)
            ->orWhere('phone', 'like', "%{$localDigits}")
            ->first();

        if ($contact) {
            $contact->touchLastContact();

            return $contact;
        }

        // Auto-create new contact from WhatsApp profile
        $profileName = $this->contactData['profile']['name'] ?? null;
        $firstName = 'WhatsApp';
        $lastName = 'Contact';

        if (! empty($profileName)) {
            $parts = explode(' ', trim($profileName), 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? null;
        }

        return Contact::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $e164Phone ?: $rawPhone,
            'lead_source' => LeadSource::WhatsApp,
            'status' => ContactStatus::Lead,
            'preferred_language' => 'en',
            'last_contact_at' => now(),
        ]);
    }

    /**
     * Extract type, body and media from WhatsApp message payload.
     *
     * @return array{type: string, body: ?string, media_url: ?string, media_mime_type: ?string, media_metadata: ?array<string, mixed>}
     */
    protected function extractMessageContent(): array
    {
        $type = $this->messageData['type'] ?? 'text';
        $body = null;
        $mediaUrl = null;
        $mediaMimeType = null;
        $mediaMetadata = null;

        switch ($type) {
            case 'text':
                $body = $this->messageData['text']['body'] ?? null;
                break;

            case 'image':
                $img = $this->messageData['image'] ?? [];
                $body = $img['caption'] ?? null;
                $mediaUrl = $img['id'] ?? null;
                $mediaMimeType = $img['mime_type'] ?? null;
                $mediaMetadata = [
                    'id' => $mediaUrl,
                    'caption' => $body,
                    'mime_type' => $mediaMimeType,
                    'sha256' => $img['sha256'] ?? null,
                ];
                break;

            case 'document':
                $doc = $this->messageData['document'] ?? [];
                $body = $doc['caption'] ?? ($doc['filename'] ?? null);
                $mediaUrl = $doc['id'] ?? null;
                $mediaMimeType = $doc['mime_type'] ?? null;
                $mediaMetadata = [
                    'id' => $mediaUrl,
                    'filename' => $doc['filename'] ?? null,
                    'caption' => $doc['caption'] ?? null,
                    'mime_type' => $mediaMimeType,
                    'sha256' => $doc['sha256'] ?? null,
                ];
                break;

            case 'audio':
            case 'voice':
                $audio = $this->messageData['audio'] ?? [];
                $body = '[Audio Message]';
                $mediaUrl = $audio['id'] ?? null;
                $mediaMimeType = $audio['mime_type'] ?? null;
                $mediaMetadata = [
                    'id' => $mediaUrl,
                    'mime_type' => $mediaMimeType,
                    'voice' => $audio['voice'] ?? false,
                ];
                break;

            case 'video':
                $video = $this->messageData['video'] ?? [];
                $body = $video['caption'] ?? '[Video Message]';
                $mediaUrl = $video['id'] ?? null;
                $mediaMimeType = $video['mime_type'] ?? null;
                $mediaMetadata = [
                    'id' => $mediaUrl,
                    'caption' => $body,
                    'mime_type' => $mediaMimeType,
                ];
                break;

            case 'button':
                $body = $this->messageData['button']['text'] ?? null;
                break;

            case 'interactive':
                $interactive = $this->messageData['interactive'] ?? [];
                $interactiveType = $interactive['type'] ?? null;

                if ($interactiveType === 'button_reply') {
                    $body = $interactive['button_reply']['title'] ?? null;
                    $mediaMetadata = ['type' => 'button_reply', 'id' => $interactive['button_reply']['id'] ?? null];
                } elseif ($interactiveType === 'list_reply') {
                    $body = $interactive['list_reply']['title'] ?? null;
                    $mediaMetadata = ['type' => 'list_reply', 'id' => $interactive['list_reply']['id'] ?? null];
                } else {
                    $body = '[Interactive Selection]';
                }
                break;

            case 'reaction':
                $body = $this->messageData['reaction']['emoji'] ?? null;
                $mediaMetadata = ['message_id' => $this->messageData['reaction']['message_id'] ?? null];
                break;

            case 'location':
                $loc = $this->messageData['location'] ?? [];
                $lat = $loc['latitude'] ?? '';
                $lng = $loc['longitude'] ?? '';
                $body = "Location: {$lat}, {$lng}";
                $mediaMetadata = [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'name' => $loc['name'] ?? null,
                    'address' => $loc['address'] ?? null,
                ];
                break;

            default:
                $body = $this->messageData['text']['body'] ?? null;
                break;
        }

        return [
            'type' => $type,
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'media_metadata' => $mediaMetadata,
        ];
    }

    /**
     * Resolve account id from event or phone number id.
     */
    protected function resolveAccountId(): ?int
    {
        if ($this->event->whatsapp_account_id) {
            return $this->event->whatsapp_account_id;
        }

        if (! empty($this->phoneNumberId)) {
            $id = WhatsAppAccount::where('phone_number_id', $this->phoneNumberId)->value('id');
            if ($id) {
                return $id;
            }
        }

        return WhatsAppAccount::default()->value('id');
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $e): void
    {
        $this->event->markFailed($e->getMessage());
    }
}
