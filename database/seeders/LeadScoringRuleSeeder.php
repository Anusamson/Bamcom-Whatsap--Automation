<?php

namespace Database\Seeders;

use App\Models\LeadScoringRule;
use Illuminate\Database\Seeder;

class LeadScoringRuleSeeder extends Seeder
{
    /**
     * Default initial scoring rules for CRM lifecycle events.
     *
     * @var array<int, array{name: string, event_key: string, category: string, points: int, description: string, is_active: bool, allow_multiple: bool}>
     */
    public const DEFAULT_RULES = [
        [
            'name' => 'New Enquiry',
            'event_key' => 'new_enquiry',
            'category' => 'discovery',
            'points' => 5,
            'description' => 'Initial inquiry registered via WhatsApp or CRM contact touchpoint.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Property Identified',
            'event_key' => 'property_identified',
            'category' => 'discovery',
            'points' => 5,
            'description' => 'Specific estate, plot, or property title identified and linked to lead.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Location Supplied',
            'event_key' => 'location_supplied',
            'category' => 'qualification',
            'points' => 5,
            'description' => 'Client specifies preferred location, corridor, or state (e.g. Epe, Lekki, Ibeju).',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Price Enquiry',
            'event_key' => 'price_enquiry',
            'category' => 'engagement',
            'points' => 5,
            'description' => 'Client actively inquires about official price or current promotional rates.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Budget Supplied',
            'event_key' => 'budget_supplied',
            'category' => 'qualification',
            'points' => 10,
            'description' => 'Client provides minimum/maximum financial budget or investment capacity.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Payment Plan Enquiry',
            'event_key' => 'payment_plan_enquiry',
            'category' => 'intent',
            'points' => 10,
            'description' => 'Client requests installment breakdown, spread options, or milestone schedule.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Purchase Within 30 Days',
            'event_key' => 'purchase_within_30_days',
            'category' => 'intent',
            'points' => 15,
            'description' => 'Immediate purchasing readiness indicated (1-30 days timeline).',
            'is_active' => true,
            'allow_multiple' => false,
        ],
        [
            'name' => 'Inspection Request',
            'event_key' => 'inspection_request',
            'category' => 'engagement',
            'points' => 20,
            'description' => 'Physical site inspection requested or scheduled for estate visit.',
            'is_active' => true,
            'allow_multiple' => true,
        ],
        [
            'name' => 'Inspection Completed',
            'event_key' => 'inspection_completed',
            'category' => 'engagement',
            'points' => 20,
            'description' => 'Client physically attended and completed estate site inspection.',
            'is_active' => true,
            'allow_multiple' => true,
        ],
        [
            'name' => 'Payment Process Request',
            'event_key' => 'payment_process_request',
            'category' => 'closing',
            'points' => 25,
            'description' => 'Client requested verified bank account details or subscription payment process.',
            'is_active' => true,
            'allow_multiple' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::DEFAULT_RULES as $rule) {
            LeadScoringRule::updateOrCreate(
                ['event_key' => $rule['event_key']],
                $rule
            );
        }
    }
}
