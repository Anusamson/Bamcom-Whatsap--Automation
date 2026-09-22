<?php

namespace App\Services\WhatsApp;

use App\Enums\MessageDeliveryStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Services\Conversation\ConversationService;
use Exception;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageService
{
    public function __construct(
        protected WhatsAppClient $client
    ) {}

    /**
     * Send an outbound text message to a contact or phone number.
     *
     * @param  string  $to  Phone number (normalized or local format)
     * @param  string  $body  Text message content
     * @param  ?Contact  $contact  Optional associated CRM contact
     * @param  ?User  $sender  Optional user initiating message
     * @return array{success: bool, message_id?: string, response?: array<string, mixed>, error?: string}
     */
    public function sendTextMessage(string $to, string $body, ?Contact $contact = null, ?User $sender = null): array
    {
        $normalizedPhone = $this->normalizePhone($to);

        try {
            $response = $this->client->sendTextMessage($normalizedPhone, $body);

            $messageId = $response['messages'][0]['id'] ?? null;

            if ($messageId) {
                $account = WhatsAppAccount::default()->first();
                WhatsAppMessage::create([
                    'whatsapp_account_id' => $account?->id,
                    'contact_id' => $contact?->id,
                    'meta_message_id' => $messageId,
                    'direction' => 'outbound',
                    'sender_phone' => $account?->display_phone_number ?: (string) config('whatsapp.phone_number_id', 'Bamcom'),
                    'recipient_phone' => $normalizedPhone,
                    'message_type' => 'text',
                    'body' => $body,
                    'status' => 'sent',
                    'payload' => $response,
                    'sent_at' => now(),
                ]);

                if ($contact && ! Message::where('meta_message_id', $messageId)->exists()) {
                    /** @var ConversationService $convService */
                    $convService = app(ConversationService::class);
                    $conversation = $convService->findOrCreateActiveConversation($contact, $account?->id);
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'sender_type' => $sender ? 'user' : 'system',
                        'sender_id' => $sender?->id,
                        'meta_message_id' => $messageId,
                        'direction' => 'outbound',
                        'sender_phone' => $account?->display_phone_number ?: (string) config('whatsapp.phone_number_id', 'Bamcom'),
                        'recipient_phone' => $normalizedPhone,
                        'type' => 'text',
                        'body' => $body,
                        'delivery_status' => MessageDeliveryStatus::Sent,
                        'is_read' => false,
                        'sent_at' => now(),
                        'payload' => $response,
                    ]);
                    $conversation->update(['last_message_at' => now()]);
                }
            }

            if ($contact) {
                $contact->touchLastContact();
                $this->logMessageActivity($contact, $sender, 'whatsapp_text_sent', $body, $messageId);
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'response' => $response,
            ];
        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp text to {$to}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an outbound pre-approved template message.
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $templateName  Meta template name (e.g. 'inspection_booking_confirmation')
     * @param  array<int, string|int|float>  $bodyParameters  Positional parameter values for {{1}}, {{2}}, etc.
     * @param  string  $languageCode  Template language code
     * @param  ?Contact  $contact  Associated CRM contact
     * @param  ?User  $sender  User initiating message
     * @return array{success: bool, message_id?: string, response?: array<string, mixed>, error?: string}
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        array $bodyParameters = [],
        string $languageCode = 'en_US',
        ?Contact $contact = null,
        ?User $sender = null
    ): array {
        $normalizedPhone = $this->normalizePhone($to);

        $components = [];
        if (! empty($bodyParameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($param): array => [
                    'type' => 'text',
                    'text' => (string) $param,
                ], $bodyParameters),
            ];
        }

        try {
            $response = $this->client->sendTemplateMessage(
                $normalizedPhone,
                $templateName,
                $languageCode,
                $components
            );

            $messageId = $response['messages'][0]['id'] ?? null;

            if ($messageId) {
                $account = WhatsAppAccount::default()->first();
                $bodyText = "Template: {$templateName}".(! empty($bodyParameters) ? ' ('.implode(', ', $bodyParameters).')' : '');

                WhatsAppMessage::create([
                    'whatsapp_account_id' => $account?->id,
                    'contact_id' => $contact?->id,
                    'meta_message_id' => $messageId,
                    'direction' => 'outbound',
                    'sender_phone' => $account?->display_phone_number ?: (string) config('whatsapp.phone_number_id', 'Bamcom'),
                    'recipient_phone' => $normalizedPhone,
                    'message_type' => 'template',
                    'body' => $bodyText,
                    'status' => 'sent',
                    'payload' => $response,
                    'sent_at' => now(),
                ]);

                if ($contact && ! Message::where('meta_message_id', $messageId)->exists()) {
                    /** @var ConversationService $convService */
                    $convService = app(ConversationService::class);
                    $conversation = $convService->findOrCreateActiveConversation($contact, $account?->id);
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'sender_type' => $sender ? 'user' : 'system',
                        'sender_id' => $sender?->id,
                        'meta_message_id' => $messageId,
                        'direction' => 'outbound',
                        'sender_phone' => $account?->display_phone_number ?: (string) config('whatsapp.phone_number_id', 'Bamcom'),
                        'recipient_phone' => $normalizedPhone,
                        'type' => 'template',
                        'body' => $bodyText,
                        'delivery_status' => MessageDeliveryStatus::Sent,
                        'is_read' => false,
                        'sent_at' => now(),
                        'payload' => $response,
                    ]);
                    $conversation->update(['last_message_at' => now()]);
                }
            }

            if ($contact) {
                $contact->touchLastContact();
                $description = "Sent WhatsApp template '{$templateName}' with params: ".implode(', ', $bodyParameters);
                $this->logMessageActivity($contact, $sender, 'whatsapp_template_sent', $description, $messageId);
            }

            return [
                'success' => true,
                'message_id' => $messageId,
                'response' => $response,
            ];
        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp template '{$templateName}' to {$to}: ".$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalize phone number to international E.164 digits without '+' as required by Meta.
     */
    public function normalizePhone(string $phone): string
    {
        // Strip everything except digits
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        // If local Nigerian 11-digit starting with 0 (e.g. 08031234567)
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        // If 10-digit without leading 0 (e.g. 8031234567)
        if (strlen($digits) === 10 && in_array(substr($digits, 0, 1), ['7', '8', '9'])) {
            return '234'.$digits;
        }

        return $digits;
    }

    /**
     * Helper to log audit activity on contact.
     */
    protected function logMessageActivity(
        Contact $contact,
        ?User $sender,
        string $activityType,
        string $description,
        ?string $metaMessageId
    ): void {
        Activity::create([
            'user_id' => $sender?->id,
            'activity_type' => $activityType,
            'description' => $description,
            'properties' => [
                'contact_id' => $contact->id,
                'contact_name' => $contact->full_name,
                'phone' => $contact->phone,
                'meta_message_id' => $metaMessageId,
            ],
        ]);
    }
}
