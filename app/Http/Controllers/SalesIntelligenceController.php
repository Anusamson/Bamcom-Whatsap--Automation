<?php

namespace App\Http\Controllers;

use App\Models\AiSalesInsight;
use App\Services\AI\SalesIntelligenceService;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesIntelligenceController extends Controller
{
    public function __construct(
        protected SalesIntelligenceService $intelligenceService,
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Display the main AI Sales Intelligence console with the latest report and historical timeline.
     */
    public function index(Request $request): Response
    {
        $dateRange = $this->analyticsService->resolveDateRange($request);
        $period = $dateRange['period'];

        // Find most recent insight for this period, or latest overall
        $currentInsight = AiSalesInsight::with('generatedByUser:id,name')
            ->where('period', $period)
            ->latest('id')
            ->first();

        // If none exists yet for this period, generate one automatically
        if (! $currentInsight) {
            $currentInsight = $this->intelligenceService->generate($request, $request->user());
            $currentInsight->load('generatedByUser:id,name');
        }

        $history = AiSalesInsight::with('generatedByUser:id,name')
            ->latest('id')
            ->take(15)
            ->get(['id', 'uuid', 'title', 'period', 'date_from', 'date_to', 'model_used', 'duration_ms', 'generated_by_user_id', 'created_at']);

        return Inertia::render('SalesIntelligence/Index', [
            'currentInsight' => $currentInsight,
            'history' => $history,
            'dateRange' => [
                'period' => $dateRange['period'],
                'label' => $dateRange['label'],
                'date_from' => $dateRange['date_from'],
                'date_to' => $dateRange['date_to'],
            ],
        ]);
    }

    /**
     * View a specific historical stored AI Sales Intelligence report by UUID.
     */
    public function show(string $uuid): Response
    {
        $currentInsight = AiSalesInsight::with('generatedByUser:id,name')
            ->where('uuid', $uuid)
            ->firstOrFail();

        $history = AiSalesInsight::with('generatedByUser:id,name')
            ->latest('id')
            ->take(15)
            ->get(['id', 'uuid', 'title', 'period', 'date_from', 'date_to', 'model_used', 'duration_ms', 'generated_by_user_id', 'created_at']);

        return Inertia::render('SalesIntelligence/Index', [
            'currentInsight' => $currentInsight,
            'history' => $history,
            'dateRange' => [
                'period' => $currentInsight->period,
                'label' => "Period: {$currentInsight->period}",
                'date_from' => $currentInsight->date_from?->toDateString() ?? '',
                'date_to' => $currentInsight->date_to?->toDateString() ?? '',
            ],
        ]);
    }

    /**
     * Trigger on-demand generation and persistent storage of a new AI Sales Intelligence report.
     */
    public function generate(Request $request): RedirectResponse
    {
        $insight = $this->intelligenceService->generate($request, $request->user());

        return redirect()->route('sales-intelligence.show', $insight->uuid)
            ->with('success', 'AI Sales Intelligence report generated and stored successfully.');
    }
}
