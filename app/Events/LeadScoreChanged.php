<?php

namespace App\Events;

use App\Enums\LeadTemperature;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a lead opportunity's score changes.
 */
class LeadScoreChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Lead $lead,
        public int $oldScore,
        public int $newScore,
        public LeadTemperature $oldTemperature,
        public LeadTemperature $newTemperature,
        public ?string $eventKey = null,
        public ?LeadScoringRule $rule = null,
        public ?User $actor = null,
        public ?int $pointsAwarded = null
    ) {
        $this->pointsAwarded ??= ($this->newScore - $this->oldScore);
    }
}
