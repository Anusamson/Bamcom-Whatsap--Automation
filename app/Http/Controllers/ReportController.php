<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Display the comprehensive CRM analytics and reporting center.
     */
    public function index(Request $request): Response
    {
        $activeReport = $request->input('report', 'lead_source');
        $dateRange = $this->analyticsService->resolveDateRange($request);

        $start = $dateRange['start'];
        $end = $dateRange['end'];

        // Compute all 8 reports directly from database queries
        $reports = [
            'lead_source' => $this->analyticsService->getLeadSourceReport($start, $end),
            'pipeline_funnel' => $this->analyticsService->getPipelineFunnelReport($start, $end),
            'sales_performance' => $this->analyticsService->getSalesPerformanceReport($start, $end),
            'agent_performance' => $this->analyticsService->getAgentPerformanceReport($start, $end),
            'inspection_conversion' => $this->analyticsService->getInspectionConversionReport($start, $end),
            'campaign_performance' => $this->analyticsService->getCampaignPerformanceReport($start, $end),
            'ai_conversations' => $this->analyticsService->getAiConversationsReport($start, $end),
            'human_handovers' => $this->analyticsService->getHumanHandoversReport($start, $end),
        ];

        return Inertia::render('Reports/Index', [
            'activeReport' => $activeReport,
            'reports' => $reports,
            'dateRange' => [
                'period' => $dateRange['period'],
                'label' => $dateRange['label'],
                'date_from' => $dateRange['date_from'],
                'date_to' => $dateRange['date_to'],
            ],
            'availableReports' => [
                ['key' => 'lead_source', 'label' => 'Lead Source', 'icon' => 'Compass', 'description' => 'Acquisition channels and revenue attribution'],
                ['key' => 'pipeline_funnel', 'label' => 'Pipeline Funnel', 'icon' => 'Filter', 'description' => 'Stage progression, volume, and drop-offs'],
                ['key' => 'sales_performance', 'label' => 'Sales Performance', 'icon' => 'TrendingUp', 'description' => 'Won revenue, win rates, and deal velocity'],
                ['key' => 'agent_performance', 'label' => 'Agent Performance', 'icon' => 'Users', 'description' => 'Sales rep quotas, inspections, and won deals'],
                ['key' => 'inspection_conversion', 'label' => 'Inspection Conversion', 'icon' => 'CalendarCheck', 'description' => 'Site visit outcomes and conversion to deals'],
                ['key' => 'campaign_performance', 'label' => 'Campaign Performance', 'icon' => 'Megaphone', 'description' => 'WhatsApp broadcast delivery, reads, and opt-outs'],
                ['key' => 'ai_conversations', 'label' => 'AI Conversations', 'icon' => 'Bot', 'description' => 'Automated conversations, tokens, and AI resolution'],
                ['key' => 'human_handovers', 'label' => 'Human Handovers', 'icon' => 'UserCheck', 'description' => 'Escalation triggers and representative handovers'],
            ],
        ]);
    }

    /**
     * Export raw report data in JSON format for the selected period.
     */
    public function export(Request $request): JsonResponse
    {
        $dateRange = $this->analyticsService->resolveDateRange($request);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $data = [
            'generated_at' => now()->toIso8601String(),
            'period' => $dateRange,
            'kpis' => $this->analyticsService->getDashboardKpis($start, $end),
            'lead_source' => $this->analyticsService->getLeadSourceReport($start, $end),
            'pipeline_funnel' => $this->analyticsService->getPipelineFunnelReport($start, $end),
            'sales_performance' => $this->analyticsService->getSalesPerformanceReport($start, $end),
            'agent_performance' => $this->analyticsService->getAgentPerformanceReport($start, $end),
            'inspection_conversion' => $this->analyticsService->getInspectionConversionReport($start, $end),
            'campaign_performance' => $this->analyticsService->getCampaignPerformanceReport($start, $end),
            'ai_conversations' => $this->analyticsService->getAiConversationsReport($start, $end),
            'human_handovers' => $this->analyticsService->getHumanHandoversReport($start, $end),
        ];

        return response()->json($data);
    }
}
