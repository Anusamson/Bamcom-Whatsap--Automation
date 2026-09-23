<?php

namespace App\Events;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched on any inspection status transition.
 */
class InspectionStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Inspection $inspection,
        public InspectionStatus $oldStatus,
        public InspectionStatus $newStatus,
        public ?User $actor = null,
        public array $metadata = []
    ) {}
}
