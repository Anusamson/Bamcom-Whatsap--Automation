<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a sales deal/opportunity is marked lost.
 */
class DealLost
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Deal $deal,
        public ?string $lostReason = null,
        public ?User $causer = null
    ) {}
}
