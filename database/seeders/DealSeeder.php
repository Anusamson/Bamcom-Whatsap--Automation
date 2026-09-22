<?php

namespace Database\Seeders;

use App\Enums\DealStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Property;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DealSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pipeline = Pipeline::where('is_default', true)->with('stages')->first() ?? Pipeline::with('stages')->first();
        $stages = $pipeline?->stages->keyBy('slug') ?? collect();

        $contacts = Contact::all();
        $properties = Property::with('activePrice')->get();
        $reps = User::where('status', 'active')->get();
        $leads = Lead::all();

        if ($contacts->isEmpty() || $properties->isEmpty()) {
            return;
        }

        $seedDeals = [
            [
                'title' => 'Pacific Palms 4-Bed Duplex Acquisition',
                'contact' => $contacts->first(),
                'property' => $properties->firstWhere('property_type', 'duplex') ?? $properties->first(),
                'value' => 138000000.00,
                'status' => DealStatus::Open,
                'stage' => $stages->get('negotiation') ?? $stages->first(),
                'expected_close_date' => Carbon::now()->addDays(20),
                'probability' => 80,
                'notes' => 'Buyer requested 6 months installment plan. Offer letter prepared.',
            ],
            [
                'title' => 'Grace Haven 500sqm Prime Land Purchase',
                'contact' => $contacts->skip(1)->first() ?? $contacts->first(),
                'property' => $properties->firstWhere('plot_size', '500sqm') ?? $properties->first(),
                'value' => 15000000.00,
                'status' => DealStatus::Won,
                'stage' => $stages->get('won') ?? $stages->last(),
                'expected_close_date' => Carbon::now()->subDays(5),
                'actual_close_date' => Carbon::now()->subDays(5),
                'probability' => 100,
                'notes' => '100% Outright payment completed. Deed of Assignment issued.',
            ],
            [
                'title' => 'Crown Heights 1,000sqm Commercial Plot',
                'contact' => $contacts->skip(2)->first() ?? $contacts->first(),
                'property' => $properties->firstWhere('property_type', 'commercial') ?? $properties->first(),
                'value' => 40000000.00,
                'status' => DealStatus::Lost,
                'stage' => $stages->get('lost') ?? $stages->last(),
                'expected_close_date' => Carbon::now()->subDays(10),
                'actual_close_date' => Carbon::now()->subDays(10),
                'lost_reason' => 'Client chose to divert capital into manufacturing plant expansion in Ogun State.',
                'probability' => 0,
                'notes' => 'Follow up in Q2 next year when new investment tranche opens.',
            ],
            [
                'title' => 'Grace Haven 300sqm Starter Plot Banking',
                'contact' => $contacts->first(),
                'property' => $properties->firstWhere('plot_size', '300sqm') ?? $properties->last(),
                'value' => 9800000.00,
                'status' => DealStatus::Open,
                'stage' => $stages->get('interested') ?? $stages->first(),
                'expected_close_date' => Carbon::now()->addDays(35),
                'probability' => 50,
                'notes' => 'First inspection scheduled for next Saturday morning.',
            ],
        ];

        foreach ($seedDeals as $data) {
            $rep = $reps->random() ?? null;
            $lead = $leads->firstWhere('contact_id', $data['contact']->id);

            Deal::firstOrCreate(
                [
                    'title' => $data['title'],
                    'contact_id' => $data['contact']->id,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'lead_id' => $lead?->id,
                    'property_id' => $data['property']->id,
                    'assigned_user_id' => $rep?->id,
                    'pipeline_id' => $pipeline?->id,
                    'pipeline_stage_id' => $data['stage']?->id,
                    'deal_value' => $data['value'],
                    'currency' => 'NGN',
                    'expected_close_date' => $data['expected_close_date'],
                    'actual_close_date' => $data['actual_close_date'] ?? null,
                    'status' => $data['status'],
                    'lost_reason' => $data['lost_reason'] ?? null,
                    'probability' => $data['probability'],
                    'notes' => $data['notes'],
                ]
            );
        }
    }
}
