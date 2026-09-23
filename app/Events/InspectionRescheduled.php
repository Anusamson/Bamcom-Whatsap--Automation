<?php

namespace App\Events;

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a site inspection is rescheduled to a new date/time.
 */
class InspectionRescheduled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Inspection $inspection,
        public string $oldDate,
        public string $oldTime,
        public string $newDate,
        public string $newTime,
        public ?User $actor = null,
        public array $metadata = []
    ) {}
}
