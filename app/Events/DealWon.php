<?php

namespace App\Events;

use App\Models\Deal;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a sales deal/opportunity is closed won.
 */
class DealWon
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Deal $deal,
        public ?User $causer = null
    ) {}
}
