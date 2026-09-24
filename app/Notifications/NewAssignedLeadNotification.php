<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAssignedLeadNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public ?User $assignedBy = null
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

        return [
            'type' => 'new_assigned_lead',
            'lead_id' => $this->lead->id,
            'lead_uuid' => $this->lead->uuid,
            'title' => 'New Lead Assigned: '.$this->lead->title,
            'message' => 'You have been assigned to lead opportunity "'.$this->lead->title.'" for contact '.($contact?->full_name ?? 'Client').'.',
            'contact_id' => $contact?->id,
            'contact_name' => $contact?->full_name ?? 'Client',
            'contact_phone' => $contact?->phone,
            'budget' => $this->lead->formatted_budget,
            'assigned_by' => $this->assignedBy?->name ?? 'System',
            'url' => route('leads.show', $this->lead->uuid ?: $this->lead->id),
            'created_at' => now()->toIso8601String(),
        ];
    }
}
