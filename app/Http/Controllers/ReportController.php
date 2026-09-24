<?php

namespace App\Http\Controllers;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Exporters\CsvReportExporter;
use App\Services\Analytics\Exporters\DocReportExporter;
use App\Services\Analytics\Exporters\PdfReportExporter;
use App\Services\Analytics\Exporters\PptReportExporter;
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
     * Export analytics and performance report in PDF, PPT, CSV, or DOC format.
     */
    public function export(
        Request $request,
        PdfReportExporter $pdfExporter,
        PptReportExporter $pptExporter,
        CsvReportExporter $csvExporter,
        DocReportExporter $docExporter
    ): \Symfony\Component\HttpFoundation\Response {
        $dateRange = $this->analyticsService->resolveDateRange($request);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        $kpis = $this->analyticsService->getDashboardKpis(
            $start,
            $end,
            $dateRange['previous_start'],
            $dateRange['previous_end']
        );

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

        $exportData = [
            'dateRange' => $dateRange,
            'kpis' => $kpis,
            'reports' => $reports,
        ];

        $format = strtolower((string) $request->input('format', 'pdf'));

        return match ($format) {
            'csv' => $csvExporter->export($exportData),
            'ppt', 'pptx' => $pptExporter->export($exportData),
            'doc', 'docx', 'word' => $docExporter->export($exportData),
            default => $pdfExporter->export($exportData),
        };
    }
}
