<?php

namespace Tests\Unit;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\DealStatus;
use App\Enums\InspectionStatus;
use App\Enums\LeadSource;
use App\Enums\LeadTemperature;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnalyticsService;
    }

    /**
     * Test resolveDateRange for standard presets and custom intervals.
     */
    public function test_resolve_date_range_presets(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');

        // 1. Today
        $today = $this->service->resolveDateRange(new Request(['period' => 'today']));
        $this->assertEquals('2026-09-24 00:00:00', $today['start']->toDateTimeString());
        $this->assertEquals('2026-09-24 23:59:59', $today['end']->toDateTimeString());
        $this->assertEquals('Today', $today['label']);

        // 2. 7 days
        $sevenDays = $this->service->resolveDateRange(new Request(['period' => '7d']));
        $this->assertEquals('2026-09-18 00:00:00', $sevenDays['start']->toDateTimeString());
        $this->assertEquals('2026-09-24 23:59:59', $sevenDays['end']->toDateTimeString());
        $this->assertEquals('Last 7 Days', $sevenDays['label']);

        // 3. 30 days (default)
        $thirtyDays = $this->service->resolveDateRange(new Request);
        $this->assertEquals('2026-08-26 00:00:00', $thirtyDays['start']->toDateTimeString());
        $this->assertEquals('2026-09-24 23:59:59', $thirtyDays['end']->toDateTimeString());
        $this->assertEquals('30d', $thirtyDays['period']);

        // 4. 90 days
        $ninetyDays = $this->service->resolveDateRange(new Request(['period' => '90d']));
        $this->assertEquals('2026-06-27 00:00:00', $ninetyDays['start']->toDateTimeString());
        $this->assertEquals('2026-09-24 23:59:59', $ninetyDays['end']->toDateTimeString());

        // 5. This Month
        $thisMonth = $this->service->resolveDateRange(new Request(['period' => 'this_month']));
        $this->assertEquals('2026-09-01 00:00:00', $thisMonth['start']->toDateTimeString());
        $this->assertEquals('2026-09-30 23:59:59', $thisMonth['end']->toDateTimeString());

        // 6. This Year
        $thisYear = $this->service->resolveDateRange(new Request(['period' => 'this_year']));
        $this->assertEquals('2026-01-01 00:00:00', $thisYear['start']->toDateTimeString());
        $this->assertEquals('2026-12-31 23:59:59', $thisYear['end']->toDateTimeString());

        // 7. Custom Range
        $custom = $this->service->resolveDateRange(new Request([
            'period' => 'custom',
            'date_from' => '2026-09-01',
            'date_to' => '2026-09-15',
        ]));
        $this->assertEquals('2026-09-01 00:00:00', $custom['start']->toDateTimeString());
        $this->assertEquals('2026-09-15 23:59:59', $custom['end']->toDateTimeString());
        $this->assertEquals('Sep 1, 2026 - Sep 15, 2026', $custom['label']);

        Carbon::setTestNow();
    }

    /**
     * Test Dashboard KPIs calculation strictly from DB records.
     */
    public function test_get_dashboard_kpis_accurate_calculations(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');
        $prevStart = Carbon::parse('2026-08-01 00:00:00');
        $prevEnd = Carbon::parse('2026-08-31 23:59:59');

        // Create Leads
        Lead::factory()->count(3)->create(['created_at' => $start->copy()->addDays(2), 'temperature' => LeadTemperature::Hot]);
        Lead::factory()->count(2)->create(['created_at' => $start->copy()->addDays(5), 'temperature' => LeadTemperature::Warm]);
        // Previous period leads
        Lead::factory()->count(2)->create(['created_at' => $prevStart->copy()->addDays(2), 'temperature' => LeadTemperature::Hot]);

        // Create Conversations
        Conversation::factory()->create([
            'status' => ConversationStatus::Open,
            'updated_at' => $start->copy()->addDays(3),
        ]);
        Conversation::factory()->create([
            'status' => ConversationStatus::Pending,
            'updated_at' => $start->copy()->addDays(4),
        ]);
        Conversation::factory()->create([
            'status' => ConversationStatus::Closed,
            'updated_at' => $start->copy()->addDays(4),
        ]);

        // Create Inspections
        Inspection::factory()->count(4)->create(['created_at' => $start->copy()->addDays(6)]);
        Inspection::factory()->count(2)->create(['created_at' => $prevStart->copy()->addDays(6)]);

        // Create Deals
        Deal::factory()->create([
            'status' => DealStatus::Open,
            'deal_value' => 5000000,
            'created_at' => $start->copy()->addDays(7),
        ]);
        Deal::factory()->create([
            'status' => DealStatus::Open,
            'deal_value' => 3000000,
            'created_at' => $start->copy()->addDays(8),
        ]);
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 12000000,
            'actual_close_date' => $start->copy()->addDays(10),
            'created_at' => $start->copy()->addDays(1),
        ]);

        $kpis = $this->service->getDashboardKpis($start, $end, $prevStart, $prevEnd);

        // 1. New Leads: 5 in period, 2 in previous -> +150%
        $this->assertEquals(5, $kpis['new_leads']['value']);
        $this->assertEquals(2, $kpis['new_leads']['previous']);
        $this->assertEquals(150.0, $kpis['new_leads']['change_percent']);

        // 2. Hot Leads: 3 in period, 2 in previous -> +50%
        $this->assertEquals(3, $kpis['hot_leads']['value']);
        $this->assertEquals(2, $kpis['hot_leads']['previous']);
        $this->assertEquals(50.0, $kpis['hot_leads']['change_percent']);

        // 3. Active Conversations: 2 in period (Open + Pending)
        $this->assertEquals(2, $kpis['active_conversations']['value']);

        // 4. Inspections: 4 in period, 2 in previous -> +100%
        $this->assertEquals(4, $kpis['inspections']['value']);
        $this->assertEquals(2, $kpis['inspections']['previous']);
        $this->assertEquals(100.0, $kpis['inspections']['change_percent']);

        // 5. Open Deals: 2
        $this->assertEquals(2, $kpis['open_deals']['value']);

        // 6. Pipeline Value: 5,000,000 + 3,000,000 = 8,000,000
        $this->assertEquals(8000000.0, $kpis['pipeline_value']['value']);
        $this->assertStringContainsString('8,000,000.00', $kpis['pipeline_value']['formatted']);

        // 7. Sales Won: 12,000,000, 1 deal
        $this->assertEquals(12000000.0, $kpis['sales_won']['value']);
        $this->assertEquals(1, $kpis['sales_won']['count']);

        // 8. Conversion Rate: 1 won out of 3 total deals = 33.3%
        $this->assertEquals(33.3, $kpis['conversion_rate']['value']);
        $this->assertEquals('33.3%', $kpis['conversion_rate']['formatted']);
    }

    /**
     * Test Lead Source Report calculation and revenue attribution.
     */
    public function test_get_lead_source_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        $leadWeb1 = Lead::factory()->create([
            'lead_source' => LeadSource::Website,
            'created_at' => $start->copy()->addDay(),
        ]);
        $leadWeb2 = Lead::factory()->create([
            'lead_source' => LeadSource::Website,
            'created_at' => $start->copy()->addDays(2),
        ]);
        $leadRef = Lead::factory()->create([
            'lead_source' => LeadSource::Referral,
            'created_at' => $start->copy()->addDays(3),
        ]);

        // Won deal for Website lead
        Deal::factory()->create([
            'lead_id' => $leadWeb1->id,
            'status' => DealStatus::Won,
            'deal_value' => 15000000,
            'created_at' => $start->copy()->addDays(5),
        ]);

        $report = $this->service->getLeadSourceReport($start, $end);

        $this->assertEquals(3, $report['total_leads']);
        $this->assertCount(2, $report['sources']);

        // Find Website source item
        $webSource = collect($report['sources'])->firstWhere('source', LeadSource::Website->value);
        $this->assertNotNull($webSource);
        $this->assertEquals(2, $webSource['count']);
        $this->assertEquals(66.7, $webSource['percentage']);
        $this->assertEquals(15000000.0, $webSource['won_revenue']);
    }

    /**
     * Test Pipeline Funnel Report progression and conversion tracking.
     */
    public function test_get_pipeline_funnel_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        $stage1 = PipelineStage::factory()->create(['name' => 'Initial Contact', 'order_column' => 1]);
        $stage2 = PipelineStage::factory()->create(['name' => 'Inspection Scheduled', 'order_column' => 2]);

        Lead::factory()->count(4)->create(['pipeline_stage_id' => $stage1->id, 'created_at' => $start->copy()->addDay()]);
        Deal::factory()->create(['pipeline_stage_id' => $stage1->id, 'deal_value' => 2000000, 'created_at' => $start->copy()->addDay()]);

        Lead::factory()->count(2)->create(['pipeline_stage_id' => $stage2->id, 'created_at' => $start->copy()->addDays(2)]);
        Deal::factory()->create(['pipeline_stage_id' => $stage2->id, 'deal_value' => 5000000, 'created_at' => $start->copy()->addDays(2)]);

        $report = $this->service->getPipelineFunnelReport($start, $end);

        $this->assertNotEmpty($report['stages']);
        $stage1Report = collect($report['stages'])->firstWhere('stage_id', $stage1->id);
        $this->assertEquals(4, $stage1Report['leads_count']);
        $this->assertEquals(1, $stage1Report['deals_count']);
        $this->assertEquals(2000000.0, $stage1Report['total_value']);
    }

    /**
     * Test Sales Performance Report.
     */
    public function test_get_sales_performance_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 10000000,
            'actual_close_date' => $start->copy()->addDays(5),
            'created_at' => $start->copy()->addDays(5),
        ]);
        Deal::factory()->create([
            'status' => DealStatus::Won,
            'deal_value' => 20000000,
            'actual_close_date' => $start->copy()->addDays(6),
            'created_at' => $start->copy()->addDays(6),
        ]);
        Deal::factory()->create([
            'status' => DealStatus::Lost,
            'deal_value' => 5000000,
            'created_at' => $start->copy()->addDays(7),
        ]);
        Deal::factory()->create([
            'status' => DealStatus::Open,
            'deal_value' => 8000000,
            'created_at' => $start->copy()->addDays(8),
        ]);

        $report = $this->service->getSalesPerformanceReport($start, $end);

        $this->assertEquals(4, $report['total_deals']);
        $this->assertEquals(2, $report['won_deals']);
        $this->assertEquals(1, $report['lost_deals']);
        $this->assertEquals(1, $report['open_deals']);
        $this->assertEquals(30000000.0, $report['won_revenue']);
        $this->assertEquals(5000000.0, $report['lost_revenue']);
        // 2 won out of 3 closed (2 won + 1 lost) = 66.7%
        $this->assertEquals(66.7, $report['win_rate']);
        // Average won deal size: 30m / 2 = 15m
        $this->assertEquals(15000000.0, $report['avg_deal_size']);
        $this->assertNotEmpty($report['trends']);
    }

    /**
     * Test Agent Performance Report.
     */
    public function test_get_agent_performance_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        $agentA = User::factory()->create(['name' => 'Agent Alice', 'status' => 'active']);
        $agentB = User::factory()->create(['name' => 'Agent Bob', 'status' => 'active']);

        // Agent Alice stats
        Lead::factory()->count(3)->create(['assigned_user_id' => $agentA->id, 'created_at' => $start->copy()->addDay()]);
        Inspection::factory()->create([
            'representative_id' => $agentA->id,
            'status' => InspectionStatus::Completed,
            'created_at' => $start->copy()->addDay(),
        ]);
        Deal::factory()->create([
            'assigned_user_id' => $agentA->id,
            'status' => DealStatus::Won,
            'deal_value' => 25000000,
            'created_at' => $start->copy()->addDay(),
        ]);

        // Agent Bob stats
        Lead::factory()->count(1)->create(['assigned_user_id' => $agentB->id, 'created_at' => $start->copy()->addDay()]);

        $report = $this->service->getAgentPerformanceReport($start, $end);

        $aliceData = collect($report['agents'])->firstWhere('agent_id', $agentA->id);
        $this->assertNotNull($aliceData);
        $this->assertEquals(3, $aliceData['assigned_leads']);
        $this->assertEquals(1, $aliceData['completed_inspections']);
        $this->assertEquals(1, $aliceData['won_deals']);
        $this->assertEquals(25000000.0, $aliceData['won_revenue']);
        $this->assertEquals(100.0, $aliceData['win_rate']);
    }

    /**
     * Test Inspection Conversion Report.
     */
    public function test_get_inspection_conversion_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        $contact = Contact::factory()->create();

        Inspection::factory()->create([
            'contact_id' => $contact->id,
            'status' => InspectionStatus::Completed,
            'created_at' => $start->copy()->addDays(2),
        ]);
        Inspection::factory()->create([
            'status' => InspectionStatus::Scheduled,
            'created_at' => $start->copy()->addDays(3),
        ]);
        Inspection::factory()->create([
            'status' => InspectionStatus::Cancelled,
            'created_at' => $start->copy()->addDays(4),
        ]);
        Inspection::factory()->create([
            'status' => InspectionStatus::NoShow,
            'created_at' => $start->copy()->addDays(5),
        ]);

        // Won deal tied to contact with completed inspection
        Deal::factory()->create([
            'contact_id' => $contact->id,
            'status' => DealStatus::Won,
            'deal_value' => 40000000,
            'created_at' => $start->copy()->addDays(10),
        ]);

        $report = $this->service->getInspectionConversionReport($start, $end);

        $this->assertEquals(4, $report['total']);
        $this->assertEquals(1, $report['completed']);
        $this->assertEquals(1, $report['scheduled']);
        $this->assertEquals(1, $report['cancelled']);
        $this->assertEquals(1, $report['no_show']);
        // 1 completed / 4 total = 25.0%
        $this->assertEquals(25.0, $report['completion_rate']);
        $this->assertEquals(1, $report['won_deals_from_inspection']);
        // 1 converted deal / 1 completed inspection = 100.0%
        $this->assertEquals(100.0, $report['inspection_to_deal_rate']);
    }

    /**
     * Test WhatsApp Campaign Performance Report.
     */
    public function test_get_campaign_performance_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        Campaign::factory()->create([
            'name' => 'Abuja Promo Blast',
            'total_recipients' => 100,
            'sent_count' => 100,
            'delivered_count' => 90,
            'read_count' => 60,
            'failed_count' => 10,
            'opted_out_count' => 2,
            'created_at' => $start->copy()->addDays(3),
        ]);

        $report = $this->service->getCampaignPerformanceReport($start, $end);

        $this->assertEquals(1, $report['total_campaigns']);
        $this->assertEquals(100, $report['total_recipients']);
        $this->assertEquals(100, $report['sent_count']);
        $this->assertEquals(90, $report['delivered_count']);
        $this->assertEquals(60, $report['read_count']);
        $this->assertEquals(10, $report['failed_count']);
        $this->assertEquals(2, $report['opted_out_count']);
        $this->assertEquals(90.0, $report['delivery_rate']);
        $this->assertEquals(66.7, $report['read_rate']);
        $this->assertEquals(2.0, $report['opt_out_rate']);
    }

    /**
     * Test AI Conversations Report.
     */
    public function test_get_ai_conversations_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        Conversation::factory()->create([
            'mode' => ConversationMode::Ai,
            'created_at' => $start->copy()->addDays(2),
        ]);
        Conversation::factory()->create([
            'mode' => ConversationMode::Hybrid,
            'created_at' => $start->copy()->addDays(2),
        ]);
        Conversation::factory()->create([
            'mode' => ConversationMode::Human,
            'created_at' => $start->copy()->addDays(3),
        ]);

        // Insert AI execution log
        DB::table('ai_execution_logs')->insert([
            'uuid' => (string) Str::uuid(),
            'agent_name' => 'BamcomSalesAgent',
            'provider' => 'gemini',
            'model' => 'gemini-1.5-pro',
            'user_message' => 'Hello pricing',
            'total_tokens' => 500,
            'duration_ms' => 350.0,
            'created_at' => $start->copy()->addDays(2),
            'updated_at' => $start->copy()->addDays(2),
        ]);

        Activity::create([
            'activity_type' => 'ai_handover_executed',
            'description' => 'Escalated to rep',
            'created_at' => $start->copy()->addDays(4),
        ]);

        $report = $this->service->getAiConversationsReport($start, $end);

        $this->assertEquals(3, $report['total_conversations']);
        $this->assertEquals(1, $report['ai_conversations']);
        $this->assertEquals(1, $report['hybrid_conversations']);
        $this->assertEquals(1, $report['human_conversations']);
        $this->assertEquals(1, $report['total_ai_executions']);
        $this->assertEquals(500, $report['total_tokens']);
        $this->assertEquals(350.0, $report['avg_duration_ms']);
    }

    /**
     * Test Human Handovers Report.
     */
    public function test_get_human_handovers_report(): void
    {
        $start = Carbon::parse('2026-09-01 00:00:00');
        $end = Carbon::parse('2026-09-30 23:59:59');

        Activity::create([
            'activity_type' => 'ai_handover_executed',
            'description' => 'Escalation for price negotiation',
            'properties' => [
                'trigger_label' => 'Price Negotiation',
                'assigned_user_name' => 'Alice Admin',
            ],
            'created_at' => $start->copy()->addDays(2),
        ]);

        Activity::create([
            'activity_type' => 'human_handover_requested',
            'description' => 'User asked for human agent',
            'properties' => [
                'trigger_label' => 'Customer Request',
                'assigned_user_name' => 'Alice Admin',
            ],
            'created_at' => $start->copy()->addDays(3),
        ]);

        $report = $this->service->getHumanHandoversReport($start, $end);

        $this->assertEquals(2, $report['total_handovers']);
        $this->assertCount(2, $report['triggers']);
        $this->assertCount(1, $report['top_representatives']);
        $this->assertEquals('Alice Admin', $report['top_representatives'][0]['representative']);
        $this->assertEquals(2, $report['top_representatives'][0]['count']);
    }
}
