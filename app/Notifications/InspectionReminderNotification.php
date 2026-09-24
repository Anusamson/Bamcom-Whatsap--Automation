<?php

namespace App\Notifications;

use App\Models\Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InspectionReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Inspection $inspection,
        public string $timing = 'Upcoming'
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
        $contact = $this->inspection->contact;
        $location = $this->inspection->target_title ?: 'Site';
        $timeStr = $this->inspection->formatted_date_time ?: 'TBD';

        return [
            'type' => 'inspection_reminder',
            'inspection_id' => $this->inspection->id,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'estate_name' => $location,
            'scheduled_at' => $timeStr,
            'timing' => $this->timing,
            'title' => 'Inspection Reminder: '.$location,
            'message' => 'Reminder: You have an inspection at '.$location.' with '.($contact?->full_name ?? 'Client').' scheduled for '.$timeStr.'.',
            'url' => route('inspections.index'),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
