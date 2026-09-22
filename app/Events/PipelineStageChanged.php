<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a lead moves from one pipeline stage to another.
 */
class PipelineStageChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Lead $lead,
        public ?PipelineStage $previousStage,
        public PipelineStage $newStage,
        public ?User $causer = null
    ) {}
}
