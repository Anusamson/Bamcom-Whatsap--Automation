<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a contact or lead expresses interest in a real estate property.
 */
class PropertyInterestAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $interestData
     */
    public function __construct(
        public Contact $contact,
        public ?Property $property = null,
        public ?Lead $lead = null,
        public array $interestData = [],
        public ?User $causer = null
    ) {}
}
