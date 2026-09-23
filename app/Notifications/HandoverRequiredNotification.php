<?php

namespace App\Notifications;

use App\Enums\HandoverTrigger;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HandoverRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public HandoverTrigger $trigger,
        public string $reason
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
            'conversation_id' => $this->conversation->id,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'trigger' => $this->trigger->value,
            'trigger_label' => $this->trigger->label(),
            'priority' => $this->trigger->priority(),
            'reason' => $this->reason,
            'title' => "Handover Required: {$this->trigger->label()}",
            'message' => "Client {$contact?->full_name} requires human representative attention ({$this->trigger->label()}). Reason: {$this->reason}",
            'escalated_at' => now()->toIso8601String(),
        ];
    }
}
