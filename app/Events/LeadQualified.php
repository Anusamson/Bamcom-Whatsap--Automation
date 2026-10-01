<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a sales lead meets qualification criteria.
 */
class LeadQualified
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Lead $lead,
        public ?User $causer = null,
        public array $context = []
    ) {}
}
