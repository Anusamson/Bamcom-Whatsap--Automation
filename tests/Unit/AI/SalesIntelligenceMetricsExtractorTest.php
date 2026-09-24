<?php

namespace Tests\Unit\AI;

use App\Enums\ConversationMode;
use App\Enums\DealStatus;
use App\Enums\InspectionStatus;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Estate;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Property;
use App\Services\AI\SalesIntelligenceMetricsExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesIntelligenceMetricsExtractorTest extends TestCase
{
    use RefreshDatabase;

    protected SalesIntelligenceMetricsExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new SalesIntelligenceMetricsExtractor;
    }

    /**
     * Test verified extraction of all 9 intelligence topics without fabrication.
     */
    public function test_extracts_all_nine_verified_metrics(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        // 1. Frequently Requested Estates
        $estate1 = Estate::factory()->create(['name' => 'Peace Court Estate']);
        $estate2 = Estate::factory()->create(['name' => 'Grace Villa Estate']);
        $property1 = Property::factory()->create(['estate_id' => $estate1->id]);

        Lead::factory()->count(3)->create([
            'property_id' => $property1->id,
            'created_at' => $start->copy()->addDays(2),
        ]);
        Lead::factory()->create([
            'preferred_location' => 'Grace Villa Estate, Abuja',
            'created_at' => $start->copy()->addDays(3),
        ]);

        $conversation = Conversation::factory()->create(['mode' => ConversationMode::Ai]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'I would love to inspect Peace Court Estate this Saturday',
            'created_at' => $start->copy()->addDays(4),
        ]);

        // 2. Common Customer Questions
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'What type of C of O title document does this plot have?',
            'created_at' => $start->copy()->addDays(5),
        ]);
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'How much is the total price and can I do installment payment plan?',
            'created_at' => $start->copy()->addDays(6),
        ]);

        // 3. Common Objections
        Deal::factory()->create([
            'status' => DealStatus::Lost,
            'deal_value' => 25000000,
            'lost_reason' => 'Client expressed budget constraint and price too high',
            'created_at' => $start->copy()->addDays(7),
        ]);

        // 4. Requested Price Ranges
        Lead::factory()->create([
            'budget_min' => 15000000,
            'budget_max' => 22000000,
            'created_at' => $start->copy()->addDays(8),
        ]);
        Deal::factory()->create([
            'deal_value' => 35000000,
            'created_at' => $start->copy()->addDays(9),
        ]);

        // 5. Requested Payment Plans
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => 'I prefer a 12 months installment payment spread',
            'created_at' => $start->copy()->addDays(10),
        ]);

        // 6. Handover Reasons
        Activity::create([
            'activity_type' => 'ai_handover_executed',
            'description' => 'Escalated due to price discount request',
            'properties' => [
                'trigger_label' => 'Price Negotiation',
            ],
            'created_at' => $start->copy()->addDays(11),
        ]);

        // 7. Lost Deal Reasons
        Deal::factory()->create([
            'status' => DealStatus::Lost,
            'deal_value' => 40000000,
            'lost_reason' => 'Preferred closer location to town',
            'created_at' => $start->copy()->addDays(12),
        ]);

        // 8. Lead-to-Inspection Conversion
        $contactWithInsp = Contact::factory()->create();
        Lead::factory()->create([
            'contact_id' => $contactWithInsp->id,
            'created_at' => $start->copy()->addDays(13),
        ]);
        Inspection::factory()->create([
            'contact_id' => $contactWithInsp->id,
            'status' => InspectionStatus::Scheduled,
            'created_at' => $start->copy()->addDays(14),
        ]);

        // 9. Inspection-to-Sale Conversion
        $inspectedBuyer = Contact::factory()->create();
        Inspection::factory()->create([
            'contact_id' => $inspectedBuyer->id,
            'status' => InspectionStatus::Completed,
            'created_at' => $start->copy()->addDays(15),
        ]);
        Deal::factory()->create([
            'contact_id' => $inspectedBuyer->id,
            'status' => DealStatus::Won,
            'deal_value' => 50000000,
            'created_at' => $start->copy()->addDays(16),
        ]);

        // Execute Extractor
        $metrics = $this->extractor->extractMetrics($start, $end);

        // Verify all 9 sections exist
        $this->assertArrayHasKey('frequently_requested_estates', $metrics);
        $this->assertArrayHasKey('common_customer_questions', $metrics);
        $this->assertArrayHasKey('common_objections', $metrics);
        $this->assertArrayHasKey('requested_price_ranges', $metrics);
        $this->assertArrayHasKey('requested_payment_plans', $metrics);
        $this->assertArrayHasKey('handover_reasons', $metrics);
        $this->assertArrayHasKey('lost_deal_reasons', $metrics);
        $this->assertArrayHasKey('lead_to_inspection_conversion', $metrics);
        $this->assertArrayHasKey('inspection_to_sale_conversion', $metrics);

        // 1. Frequently Requested Estates
        $peaceCourt = collect($metrics['frequently_requested_estates'])->firstWhere('estate', 'Peace Court Estate');
        $this->assertNotNull($peaceCourt);
        $this->assertEquals(4, $peaceCourt['count']); // 3 property leads + 1 message

        // 2. Common Customer Questions
        $this->assertNotEmpty($metrics['common_customer_questions']);
        $titleCategory = collect($metrics['common_customer_questions'])->firstWhere('category', 'Land Title & Legal Documentation');
        $this->assertNotNull($titleCategory);

        // 3. Common Objections
        $budgetObjection = collect($metrics['common_objections'])->firstWhere('objection', 'Budget / Price Resistance');
        $this->assertNotNull($budgetObjection);

        // 4. Requested Price Ranges
        $this->assertNotEmpty($metrics['requested_price_ranges']);

        // 5. Requested Payment Plans
        $plan = collect($metrics['requested_payment_plans'])->firstWhere('plan', '12 Months Installments');
        $this->assertNotNull($plan);

        // 6. Handover Reasons
        $handover = collect($metrics['handover_reasons'])->firstWhere('reason', 'Price Negotiation');
        $this->assertNotNull($handover);
        $this->assertEquals(1, $handover['count']);

        // 7. Lost Deal Reasons
        $this->assertNotEmpty($metrics['lost_deal_reasons']);

        // 8. Lead-to-Inspection Conversion
        $this->assertGreaterThan(0, $metrics['lead_to_inspection_conversion']['total_leads']);
        $this->assertGreaterThan(0, $metrics['lead_to_inspection_conversion']['conversion_rate']);

        // 9. Inspection-to-Sale Conversion
        $this->assertEquals(1, $metrics['inspection_to_sale_conversion']['completed_inspections']);
        $this->assertEquals(1, $metrics['inspection_to_sale_conversion']['won_deals']);
        $this->assertEquals(100.0, $metrics['inspection_to_sale_conversion']['conversion_rate']);
        $this->assertEquals(50000000.0, $metrics['inspection_to_sale_conversion']['won_revenue']);
    }
}
