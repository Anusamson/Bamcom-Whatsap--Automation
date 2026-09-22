<?php

namespace App\Jobs;

use App\Enums\MessageDeliveryStatus;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WhatsAppWebhookEvent $event
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Skip if already processed
        if ($this->event->status === 'processed') {
            return;
        }

        try {
            $payload = $this->event->payload;
            $entries = $payload['entry'] ?? [];

            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];

                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    $metadata = $value['metadata'] ?? [];
                    $displayPhone = $metadata['display_phone_number'] ?? null;
                    $phoneNumberId = $metadata['phone_number_id'] ?? null;

                    // Link account if not already linked
                    if ($this->event->whatsapp_account_id === null && ! empty($phoneNumberId)) {
                        $accountId = WhatsAppAccount::where('phone_number_id', $phoneNumberId)->value('id');
                        if ($accountId) {
                            $this->event->update(['whatsapp_account_id' => $accountId]);
                        }
                    }

                    // Process incoming messages
                    if (! empty($value['messages']) && is_array($value['messages'])) {
                        $contactsMap = [];
                        foreach ($value['contacts'] ?? [] as $contactProfile) {
                            $waId = $contactProfile['wa_id'] ?? '';
                            if ($waId !== '') {
                                $contactsMap[$waId] = $contactProfile;
                            }
                        }

                        foreach ($value['messages'] as $message) {
                            $senderWaId = $message['from'] ?? '';
                            $contactData = $contactsMap[$senderWaId] ?? ($value['contacts'][0] ?? []);

                            ProcessIncomingMessage::dispatch(
                                $this->event,
                                $message,
                                $contactData,
                                $displayPhone,
                                $phoneNumberId
                            );
                        }
                    }

                    // Process message delivery status updates
                    if (! empty($value['statuses']) && is_array($value['statuses'])) {
                        foreach ($value['statuses'] as $statusItem) {
                            $this->processMessageStatus($statusItem);
                        }
                    }
                }
            }

            $this->event->markProcessed();
        } catch (Throwable $e) {
            Log::error("Failed to process WhatsApp webhook event [{$this->event->id}]: {$e->getMessage()}", [
                'event_id' => $this->event->id,
                'exception' => $e,
            ]);

            $this->event->markFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * Process message delivery status receipt (sent, delivered, read, failed).
     *
     * @param  array<string, mixed>  $statusItem
     */
    protected function processMessageStatus(array $statusItem): void
    {
        $metaMessageId = $statusItem['id'] ?? null;
        if (empty($metaMessageId)) {
            return;
        }

        $status = $statusItem['status'] ?? null;
        $timestamp = ! empty($statusItem['timestamp'])
            ? Carbon::createFromTimestamp($statusItem['timestamp'])
            : now();

        $errors = $statusItem['errors'] ?? [];
        $errorTitle = $errors[0]['title'] ?? ($errors[0]['message'] ?? 'Message delivery failed');

        // 1. Update authoritative CRM Message entity
        $crmMessage = Message::where('meta_message_id', $metaMessageId)->first();
        if ($crmMessage) {
            switch ($status) {
                case 'sent':
                    $crmMessage->update([
                        'delivery_status' => MessageDeliveryStatus::Sent,
                        'sent_at' => $crmMessage->sent_at ?: $timestamp,
                    ]);
                    break;

                case 'delivered':
                    $crmMessage->markDelivered($timestamp);
                    break;

                case 'read':
                    $crmMessage->markRead($timestamp);
                    break;

                case 'failed':
                    $crmMessage->markFailed($errorTitle);
                    break;
            }
        }

        // 2. Update WhatsAppMessage line record
        $message = WhatsAppMessage::where('meta_message_id', $metaMessageId)->first();
        if ($message) {
            switch ($status) {
                case 'sent':
                    $message->status = 'sent';
                    $message->sent_at = $message->sent_at ?: $timestamp;
                    break;

                case 'delivered':
                    $message->status = 'delivered';
                    $message->delivered_at = $timestamp;
                    break;

                case 'read':
                    $message->status = 'read';
                    $message->read_at = $timestamp;
                    break;

                case 'failed':
                    $message->status = 'failed';
                    $message->error_message = $errorTitle;
                    break;

                default:
                    if (! empty($status)) {
                        $message->status = $status;
                    }
                    break;
            }

            $message->save();
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $e): void
    {
        $this->event->markFailed($e->getMessage());
    }
}
