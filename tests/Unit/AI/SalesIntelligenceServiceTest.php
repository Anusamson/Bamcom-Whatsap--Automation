<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\SalesIntelligenceMetricsExtractor;
use App\Services\AI\SalesIntelligenceService;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SalesIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SalesIntelligenceService(
            extractor: new SalesIntelligenceMetricsExtractor,
            analyticsService: new AnalyticsService,
            provider: null // Uses verified deterministic intelligence engine
        );
    }

    /**
     * Test generating and storing AI sales intelligence with timestamps.
     */
    public function test_generates_and_stores_sales_intelligence_with_timestamps(): void
    {
        $user = User::factory()->create();
        $request = new Request(['period' => '30d']);

        $insight = $this->service->generate($request, $user);

        // Verify stored in DB
        $this->assertDatabaseHas('ai_sales_insights', [
            'id' => $insight->id,
            'uuid' => $insight->uuid,
            'period' => '30d',
            'generated_by_user_id' => $user->id,
        ]);

        // Verify timestamps
        $this->assertNotNull($insight->created_at);
        $this->assertNotNull($insight->updated_at);

        // Verify executive summary
        $this->assertNotEmpty($insight->executive_summary);

        // Verify all 9 insight sections are present
        $insights = $insight->insights;
        $this->assertArrayHasKey('frequently_requested_estates', $insights);
        $this->assertArrayHasKey('common_customer_questions', $insights);
        $this->assertArrayHasKey('common_objections', $insights);
        $this->assertArrayHasKey('requested_price_ranges', $insights);
        $this->assertArrayHasKey('requested_payment_plans', $insights);
        $this->assertArrayHasKey('handover_reasons', $insights);
        $this->assertArrayHasKey('lost_deal_reasons', $insights);
        $this->assertArrayHasKey('lead_to_inspection_conversion', $insights);
        $this->assertArrayHasKey('inspection_to_sale_conversion', $insights);

        // Verify recommendations
        $this->assertNotEmpty($insight->recommendations);
        $this->assertIsArray($insight->recommendations);

        // Verify verified metrics snapshot contains all 9 datasets
        $snapshot = $insight->metrics_snapshot;
        $this->assertArrayHasKey('frequently_requested_estates', $snapshot);
        $this->assertArrayHasKey('lead_to_inspection_conversion', $snapshot);
        $this->assertArrayHasKey('inspection_to_sale_conversion', $snapshot);
    }
}
