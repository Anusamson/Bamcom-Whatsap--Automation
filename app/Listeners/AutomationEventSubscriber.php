<?php

namespace App\Listeners;

use App\Events\DealLost;
use App\Events\DealWon;
use App\Events\InspectionCancelled;
use App\Events\InspectionCompleted;
use App\Events\InspectionScheduled;
use App\Events\LeadScoreChanged;
use App\Events\PipelineStageChanged;
use App\Services\Automation\AutomationEngine;
use Illuminate\Events\Dispatcher;

/**
 * Event Subscriber listening to CRM domain events and forwarding to the AutomationEngine.
 */
class AutomationEventSubscriber
{
    public function __construct(
        protected AutomationEngine $automationEngine
    ) {}

    public function handleLeadScoreChanged(LeadScoreChanged $event): void
    {
        $this->automationEngine->dispatch('lead_score_changed', $event->lead, [
            'old_score' => $event->oldScore,
            'new_score' => $event->newScore,
            'score' => $event->newScore,
            'temperature' => $event->newTemperature->value,
            'event_key' => $event->eventKey,
        ]);
    }

    public function handlePipelineStageChanged(PipelineStageChanged $event): void
    {
        $this->automationEngine->dispatch('pipeline_stage_changed', $event->lead, [
            'pipeline_id' => $event->newStage->pipeline_id,
            'stage_id' => $event->newStage->id,
            'stage_name' => $event->newStage->name,
            'from_stage_id' => $event->previousStage?->id,
            'from_stage_name' => $event->previousStage?->name,
            'to_stage_id' => $event->newStage->id,
            'to_stage_name' => $event->newStage->name,
            'new_stage_id' => $event->newStage->id,
            'previous_stage_id' => $event->previousStage?->id,
        ]);
    }

    public function handleInspectionScheduled(InspectionScheduled $event): void
    {
        $subject = $event->inspection->lead ?? $event->inspection->contact;
        if ($subject) {
            $this->automationEngine->dispatch('inspection_scheduled', $subject, [
                'inspection_id' => $event->inspection->id,
                'property_id' => $event->inspection->property_id,
                'representative_id' => $event->inspection->representative_id,
                'date' => $event->inspection->inspection_date?->toDateString(),
                'time' => $event->inspection->inspection_time,
            ]);
        }
    }

    public function handleInspectionCompleted(InspectionCompleted $event): void
    {
        $subject = $event->inspection->lead ?? $event->inspection->contact;
        if ($subject) {
            $this->automationEngine->dispatch('inspection_completed', $subject, [
                'inspection_id' => $event->inspection->id,
                'outcome' => $event->inspection->outcome,
            ]);
        }
    }

    public function handleInspectionCancelled(InspectionCancelled $event): void
    {
        $subject = $event->inspection->lead ?? $event->inspection->contact;
        if ($subject) {
            $this->automationEngine->dispatch('inspection_cancelled', $subject, [
                'inspection_id' => $event->inspection->id,
            ]);
        }
    }

    public function handleDealWon(DealWon $event): void
    {
        $subject = $event->deal->lead ?? $event->deal->contact;
        if ($subject) {
            $this->automationEngine->dispatch('deal_won', $subject, [
                'deal_id' => $event->deal->id,
                'deal_title' => $event->deal->title,
                'value' => (float) $event->deal->value,
            ]);
        }
    }

    public function handleDealLost(DealLost $event): void
    {
        $subject = $event->deal->lead ?? $event->deal->contact;
        if ($subject) {
            $this->automationEngine->dispatch('deal_lost', $subject, [
                'deal_id' => $event->deal->id,
                'deal_title' => $event->deal->title,
                'lost_reason' => $event->deal->lost_reason,
            ]);
        }
    }

    /**
     * Register listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            LeadScoreChanged::class => 'handleLeadScoreChanged',
            PipelineStageChanged::class => 'handlePipelineStageChanged',
            InspectionScheduled::class => 'handleInspectionScheduled',
            InspectionCompleted::class => 'handleInspectionCompleted',
            InspectionCancelled::class => 'handleInspectionCancelled',
            DealWon::class => 'handleDealWon',
            DealLost::class => 'handleDealLost',
        ];
    }
}
