<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HumanHandoverNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public string $triggerLabel = 'Customer Request',
        public string $reason = 'Client requested human representative intervention'
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $contact = $this->conversation->contact;

        return [
            'type' => 'human_handover',
            'conversation_id' => $this->conversation->id,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'trigger_label' => $this->triggerLabel,
            'reason' => $this->reason,
            'title' => 'Human Handover: '.($contact?->full_name ?? 'Client'),
            'message' => 'Escalation triggered for '.($contact?->full_name ?? 'Client').' ('.$this->triggerLabel.'). '.$this->reason,
            'url' => route('conversations.inbox', ['conversation_id' => $this->conversation->id]),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
