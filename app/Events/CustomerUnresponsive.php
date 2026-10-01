<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a customer/contact has been unresponsive for a specified threshold.
 */
class CustomerUnresponsive
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Contact $contact,
        public ?Lead $lead = null,
        public int $daysUnresponsive = 7,
        public array $context = []
    ) {}
}
