<?php

namespace Tests\Feature\Analytics;

use App\Enums\DealStatus;
use App\Enums\LeadTemperature;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardAndReportsTest extends TestCase
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
     * Unauthenticated user cannot access dashboard.
     */
    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    /**
     * Unauthenticated user cannot access reports.
     */
    public function test_guest_is_redirected_from_reports(): void
    {
        $response = $this->get('/reports');
        $response->assertRedirect('/login');
    }

    /**
     * Authenticated user can view dashboard with all 8 KPI widgets and charts.
     */
    public function test_user_can_view_dashboard_with_all_kpis(): void
    {
        Lead::factory()->count(2)->create(['temperature' => LeadTemperature::Hot]);
        Deal::factory()->create(['status' => DealStatus::Open, 'deal_value' => 5000000]);
        Inspection::factory()->count(1)->create();
        Activity::create([
            'activity_type' => 'status_changed',
            'description' => 'Test activity',
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('kpis.new_leads.value')
            ->has('kpis.hot_leads.value')
            ->has('kpis.active_conversations.value')
            ->has('kpis.inspections.value')
            ->has('kpis.open_deals.value')
            ->has('kpis.pipeline_value.value')
            ->has('kpis.sales_won.value')
            ->has('kpis.conversion_rate.value')
            ->has('pipelineFunnel.stages')
            ->has('leadSources.sources')
            ->has('salesPerformance.total_deals')
            ->has('recentActivities')
            ->where('dateRange.period', '30d')
        );
    }

    /**
     * Dashboard filters metrics by date query parameters.
     */
    public function test_dashboard_supports_date_range_filtering(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard?period=7d');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dateRange.period', '7d')
            ->where('dateRange.label', 'Last 7 Days')
        );

        $customResponse = $this->actingAs($this->user)->get('/dashboard?period=custom&date_from=2026-09-01&date_to=2026-09-10');
        $customResponse->assertOk();
        $customResponse->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dateRange.period', 'custom')
            ->where('dateRange.date_from', '2026-09-01')
            ->where('dateRange.date_to', '2026-09-10')
        );
    }

    /**
     * Authenticated user can view reports index with all 8 database-driven reports.
     */
    public function test_user_can_view_reports_index_with_all_reports(): void
    {
        Campaign::factory()->create();

        $response = $this->actingAs($this->user)->get('/reports');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('activeReport', 'lead_source')
            ->has('availableReports', 8)
            ->has('reports.lead_source')
            ->has('reports.pipeline_funnel')
            ->has('reports.sales_performance')
            ->has('reports.agent_performance')
            ->has('reports.inspection_conversion')
            ->has('reports.campaign_performance')
            ->has('reports.ai_conversations')
            ->has('reports.human_handovers')
            ->where('dateRange.period', '30d')
        );
    }

    /**
     * User can select a specific report tab.
     */
    public function test_user_can_switch_report_tabs(): void
    {
        $response = $this->actingAs($this->user)->get('/reports?report=agent_performance&period=this_month');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('activeReport', 'agent_performance')
            ->where('dateRange.period', 'this_month')
            ->where('dateRange.label', 'This Month')
        );
    }

    /**
     * User can export analytics and performance report in PDF format.
     */
    public function test_user_can_export_report_pdf(): void
    {
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 15000000,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/export?format=pdf&period=30d');

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="bamcom_analytics_report_30d_', (string) $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    /**
     * User can export analytics and performance report in PPT / PowerPoint format.
     */
    public function test_user_can_export_report_ppt(): void
    {
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 15000000,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/export?format=ppt&period=30d');

        $response->assertOk();
        $this->assertStringContainsString('presentation', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="bamcom_analytics_report_30d_', (string) $response->headers->get('content-disposition'));
        $this->assertNotEmpty($response->getContent());
    }

    /**
     * User can export analytics and performance report in CSV format.
     */
    public function test_user_can_export_report_csv(): void
    {
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 15000000,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/export?format=csv&period=30d');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="bamcom_analytics_report_30d_', (string) $response->headers->get('content-disposition'));

        // Streamed content assertion
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('BAMCOM REAL ESTATE CRM - EXECUTIVE ANALYTICS REPORT', $content);
        $this->assertStringContainsString('EXECUTIVE KEY PERFORMANCE INDICATORS', $content);
    }

    /**
     * User can export analytics and performance report in DOC format.
     */
    public function test_user_can_export_report_doc(): void
    {
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 15000000,
        ]);

        $response = $this->actingAs($this->user)->get('/reports/export?format=doc&period=30d');

        $response->assertOk();
        $this->assertStringContainsString('application/msword', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment; filename="bamcom_analytics_report_30d_', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('xmlns:w="urn:schemas-microsoft-com:office:word"', $response->getContent());
        $this->assertStringContainsString('BAMCOM REAL ESTATE CRM', $response->getContent());
    }
}
