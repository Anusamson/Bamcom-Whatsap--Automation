<?php

namespace App\Observers;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Services\Notifications\NotificationDispatchService;

class DealObserver
{
    public function __construct(
        protected NotificationDispatchService $notificationService
    ) {}

    /**
     * Handle the Deal "created" event.
     */
    public function created(Deal $deal): void
    {
        $this->notificationService->notifyDealActivity(
            deal: $deal,
            activityType: 'created',
            detail: "New deal opportunity created: \"{$deal->title}\""
        );
    }

    /**
     * Handle the Deal "updated" event.
     */
    public function updated(Deal $deal): void
    {
        if ($deal->wasChanged('pipeline_stage_id') && $deal->status === DealStatus::Open) {
            $this->notificationService->notifyDealActivity(
                deal: $deal,
                activityType: 'stage_changed',
                detail: "Deal \"{$deal->title}\" advanced to stage: ".($deal->stage?->name ?? 'Updated Stage')
            );
        }

        if ($deal->wasChanged('assigned_user_id') && $deal->assigned_user_id) {
            $this->notificationService->notifyDealActivity(
                deal: $deal,
                activityType: 'reassigned',
                detail: "Deal \"{$deal->title}\" assigned to ".($deal->assignedUser?->name ?? 'new representative')
            );
        }
    }
}
