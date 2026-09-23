<?php

namespace App\Events;

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a client fails to show up for a scheduled inspection.
 */
class InspectionNoShow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Inspection $inspection,
        public ?User $actor = null,
        public array $metadata = []
    ) {}
}
