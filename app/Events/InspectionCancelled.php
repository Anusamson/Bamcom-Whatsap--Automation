<?php

namespace App\Events;

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a site inspection is cancelled.
 */
class InspectionCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Inspection $inspection,
        public ?string $reason = null,
        public ?User $actor = null,
        public array $metadata = []
    ) {}
}
