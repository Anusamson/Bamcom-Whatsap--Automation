<?php

namespace App\Notifications;

use App\Models\Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InspectionRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Inspection $inspection
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
            'type' => 'inspection_request',
            'inspection_id' => $this->inspection->id,
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'estate_name' => $location,
            'scheduled_at' => $timeStr,
            'title' => 'Inspection Scheduled: '.$location,
            'message' => 'Site visit requested by '.($contact?->full_name ?? 'Client').' for '.$location.' scheduled on '.$timeStr.'.',
            'url' => route('inspections.index'),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
