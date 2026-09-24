<?php

namespace App\Observers;

use App\Enums\LeadTemperature;
use App\Models\Lead;
use App\Services\Notifications\NotificationDispatchService;

class LeadObserver
{
    public function __construct(
        protected NotificationDispatchService $notificationService
    ) {}

    /**
     * Handle the Lead "created" event.
     */
    public function created(Lead $lead): void
    {
        if ($lead->assigned_user_id) {
            $this->notificationService->notifyLeadAssigned($lead);
        }

        if ($lead->temperature === LeadTemperature::Hot) {
            $this->notificationService->notifyHotLead($lead, $lead->score);
        }
    }

    /**
     * Handle the Lead "updated" event.
     */
    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('assigned_user_id') && $lead->assigned_user_id) {
            $this->notificationService->notifyLeadAssigned($lead);
        }

        if ($lead->wasChanged('temperature') && $lead->temperature === LeadTemperature::Hot) {
            $this->notificationService->notifyHotLead($lead, $lead->score);
        }
    }
}
