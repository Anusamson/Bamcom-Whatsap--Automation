<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Display the main CRM executive KPI dashboard.
     */
    public function index(Request $request): Response
    {
        $dateRange = $this->analyticsService->resolveDateRange($request);

        $kpis = $this->analyticsService->getDashboardKpis(
            $dateRange['start'],
            $dateRange['end'],
            $dateRange['previous_start'],
            $dateRange['previous_end']
        );

        $pipelineFunnel = $this->analyticsService->getPipelineFunnelReport(
            $dateRange['start'],
            $dateRange['end']
        );

        $leadSources = $this->analyticsService->getLeadSourceReport(
            $dateRange['start'],
            $dateRange['end']
        );

        $salesPerformance = $this->analyticsService->getSalesPerformanceReport(
            $dateRange['start'],
            $dateRange['end']
        );

        $recentActivities = Activity::query()
            ->with(['user:id,name', 'contact:id,first_name,last_name,phone'])
            ->latest('id')
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard', [
            'kpis' => $kpis,
            'pipelineFunnel' => $pipelineFunnel,
            'leadSources' => $leadSources,
            'salesPerformance' => $salesPerformance,
            'recentActivities' => $recentActivities,
            'dateRange' => [
                'period' => $dateRange['period'],
                'label' => $dateRange['label'],
                'date_from' => $dateRange['date_from'],
                'date_to' => $dateRange['date_to'],
            ],
        ]);
    }
}
