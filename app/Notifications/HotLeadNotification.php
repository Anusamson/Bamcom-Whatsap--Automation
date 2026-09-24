<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HotLeadNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public ?int $score = null
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
        $contact = $this->lead->contact;
        $score = $this->score ?? $this->lead->score;

        return [
            'type' => 'hot_lead',
            'lead_id' => $this->lead->id,
            'lead_uuid' => $this->lead->uuid,
            'title' => 'Hot Lead Alert: '.$this->lead->title,
            'message' => 'Lead "'.$this->lead->title.'" ('.($contact?->full_name ?? 'Client').') reached HOT status with a qualification score of '.$score.'/100.',
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'score' => $score,
            'temperature' => 'hot',
            'url' => route('leads.show', $this->lead->uuid ?: $this->lead->id),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
