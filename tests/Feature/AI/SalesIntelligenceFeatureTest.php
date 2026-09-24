<?php

namespace Tests\Feature\AI;

use App\Models\AiSalesInsight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesIntelligenceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);
    }

    /**
     * Unauthenticated guest is redirected to login.
     */
    public function test_guest_is_redirected_from_sales_intelligence(): void
    {
        $response = $this->get('/sales-intelligence');
        $response->assertRedirect('/login');
    }

    /**
     * Authenticated user can view sales intelligence dashboard.
     */
    public function test_user_can_view_sales_intelligence_index(): void
    {
        $response = $this->actingAs($this->user)->get('/sales-intelligence');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesIntelligence/Index')
            ->has('currentInsight.uuid')
            ->has('currentInsight.executive_summary')
            ->has('currentInsight.insights.frequently_requested_estates')
            ->has('currentInsight.insights.common_customer_questions')
            ->has('currentInsight.insights.common_objections')
            ->has('currentInsight.insights.requested_price_ranges')
            ->has('currentInsight.insights.requested_payment_plans')
            ->has('currentInsight.insights.handover_reasons')
            ->has('currentInsight.insights.lost_deal_reasons')
            ->has('currentInsight.insights.lead_to_inspection_conversion')
            ->has('currentInsight.insights.inspection_to_sale_conversion')
            ->has('currentInsight.created_at')
            ->has('history')
            ->has('dateRange.period')
        );
    }

    /**
     * User can view a specific stored insight by UUID.
     */
    public function test_user_can_view_specific_insight(): void
    {
        $insight = AiSalesInsight::factory()->create([
            'generated_by_user_id' => $this->user->id,
            'period' => 'this_month',
        ]);

        $response = $this->actingAs($this->user)->get("/sales-intelligence/{$insight->uuid}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesIntelligence/Index')
            ->where('currentInsight.uuid', $insight->uuid)
            ->where('currentInsight.period', 'this_month')
        );
    }

    /**
     * User can trigger generation and persistent storage of new AI sales intelligence report.
     */
    public function test_user_can_generate_and_store_new_intelligence_report(): void
    {
        $response = $this->actingAs($this->user)->post('/sales-intelligence/generate', [
            'period' => '7d',
        ]);

        // Asserts redirect to the newly generated insight show page
        $insight = AiSalesInsight::latest('id')->first();
        $this->assertNotNull($insight);
        $this->assertEquals('7d', $insight->period);
        $this->assertNotNull($insight->created_at);
        $this->assertNotNull($insight->updated_at);

        $response->assertRedirect(route('sales-intelligence.show', $insight->uuid));
        $response->assertSessionHas('success');
    }

    /**
     * Sales intelligence supports custom date ranges.
     */
    public function test_supports_custom_date_ranges(): void
    {
        $response = $this->actingAs($this->user)->get('/sales-intelligence?period=custom&date_from=2026-09-01&date_to=2026-09-15');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SalesIntelligence/Index')
            ->where('dateRange.period', 'custom')
            ->where('dateRange.date_from', '2026-09-01')
            ->where('dateRange.date_to', '2026-09-15')
        );
    }
}
