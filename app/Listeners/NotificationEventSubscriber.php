<?php

namespace App\Listeners;

use App\Enums\LeadTemperature;
use App\Events\DealLost;
use App\Events\DealWon;
use App\Events\InspectionScheduled;
use App\Events\LeadScoreChanged;
use App\Services\Notifications\NotificationDispatchService;
use Illuminate\Events\Dispatcher;

/**
 * Event Subscriber listening to CRM events and dispatching staff notifications.
 */
class NotificationEventSubscriber
{
    public function __construct(
        protected NotificationDispatchService $notificationService
    ) {}

    public function handleLeadScoreChanged(LeadScoreChanged $event): void
    {
        // When a lead transitions to Hot temperature
        if ($event->newTemperature === LeadTemperature::Hot && $event->oldTemperature !== LeadTemperature::Hot) {
            $this->notificationService->notifyHotLead($event->lead, $event->newScore);
        }
    }

    public function handleInspectionScheduled(InspectionScheduled $event): void
    {
        $this->notificationService->notifyInspectionRequested($event->inspection);
    }

    public function handleDealWon(DealWon $event): void
    {
        $formattedValue = '₦'.number_format((float) $event->deal->deal_value, 2);
        $this->notificationService->notifyDealActivity(
            deal: $event->deal,
            activityType: 'won',
            detail: "Deal \"{$event->deal->title}\" was successfully marked as CLOSED WON ({$formattedValue})!",
            causer: $event->causer
        );
    }

    public function handleDealLost(DealLost $event): void
    {
        $reason = $event->deal->lost_reason ?: 'No reason provided';
        $this->notificationService->notifyDealActivity(
            deal: $event->deal,
            activityType: 'lost',
            detail: "Deal \"{$event->deal->title}\" was marked as CLOSED LOST. Reason: {$reason}",
            causer: $event->causer
        );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            LeadScoreChanged::class => 'handleLeadScoreChanged',
            InspectionScheduled::class => 'handleInspectionScheduled',
            DealWon::class => 'handleDealWon',
            DealLost::class => 'handleDealLost',
        ];
    }
}
