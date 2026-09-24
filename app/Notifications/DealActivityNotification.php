<?php

namespace App\Notifications;

use App\Models\Deal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DealActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Deal $deal,
        public string $activityType = 'updated',
        public ?string $detail = null
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
        $contact = $this->deal->contact;
        $formattedValue = '₦'.number_format((float) $this->deal->deal_value, 2);

        $actionText = match ($this->activityType) {
            'won' => 'WON',
            'lost' => 'LOST',
            'stage_changed' => 'Stage Advanced',
            'created' => 'Created',
            default => 'Updated',
        };

        $message = $this->detail
            ?: match ($this->activityType) {
                'won' => "Deal \"{$this->deal->title}\" was successfully CLOSED WON ({$formattedValue})!",
                'lost' => "Deal \"{$this->deal->title}\" was marked LOST ({$formattedValue}). Reason: ".($this->deal->lost_reason ?: 'None provided'),
                'stage_changed' => "Deal \"{$this->deal->title}\" moved to stage: ".($this->deal->stage?->name ?? 'Next Stage'),
                default => "Deal \"{$this->deal->title}\" ({$formattedValue}) was updated.",
            };

        return [
            'type' => 'deal_activity',
            'deal_id' => $this->deal->id,
            'deal_uuid' => $this->deal->uuid,
            'title' => "Deal {$actionText}: ".$this->deal->title,
            'message' => $message,
            'activity_type' => $this->activityType,
            'deal_value' => $this->deal->deal_value,
            'formatted_value' => $formattedValue,
            'status' => $this->deal->status->value,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name,
            'url' => route('deals.index'),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
