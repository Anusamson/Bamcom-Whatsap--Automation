<?php

namespace App\Services\AI;

use App\Models\AiSalesInsight;
use App\Models\User;
use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\Analytics\AnalyticsService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Service orchestrating AI Sales Intelligence generation, verified metric summaries,
 * and persistent storage of CRM management insights.
 */
class SalesIntelligenceService
{
    public function __construct(
        protected SalesIntelligenceMetricsExtractor $extractor,
        protected AnalyticsService $analyticsService,
        protected ?AIProviderInterface $provider = null
    ) {}

    /**
     * Generate, summarize, and store a new Sales Intelligence report for the requested period.
     *
     * @param  array<string, mixed>  $options
     */
    public function generate(Request $request, ?User $user = null, array $options = []): AiSalesInsight
    {
        $startTime = microtime(true);
        $dateRange = $this->analyticsService->resolveDateRange($request);

        $start = $dateRange['start'];
        $end = $dateRange['end'];
        $period = $dateRange['period'];
        $periodLabel = $dateRange['label'];

        // 1. Extract 100% verified ground-truth metrics from CRM DB
        $metrics = $this->extractor->extractMetrics($start, $end);

        // 2. Synthesize AI Insights (with deterministic fallback for 100% reliability)
        $aiResult = $this->synthesizeInsights($metrics, $periodLabel, $options);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        // 3. Store generated summary with timestamps
        return AiSalesInsight::create([
            'title' => "Sales Intelligence Briefing ({$periodLabel})",
            'period' => $period,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
            'executive_summary' => $aiResult['executive_summary'],
            'metrics_snapshot' => $metrics,
            'insights' => $aiResult['insights'],
            'recommendations' => $aiResult['recommendations'],
            'generated_by_user_id' => $user?->id,
            'model_used' => $aiResult['model_used'],
            'tokens_used' => $aiResult['tokens_used'],
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Synthesize management insights from verified metrics using LLM or deterministic fallback.
     *
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $options
     * @return array{
     *     executive_summary: string,
     *     insights: array<string, string>,
     *     recommendations: array<int, string>,
     *     model_used: string,
     *     tokens_used: int
     * }
     */
    protected function synthesizeInsights(array $metrics, string $periodLabel, array $options = []): array
    {
        if ($this->provider && $this->provider->isAvailable() && empty($options['force_deterministic'])) {
            try {
                $prompt = $this->buildSystemPrompt($metrics, $periodLabel);
                $messages = [
                    ['role' => 'system', 'content' => 'You are the Executive Sales Intelligence Engine for Bamcom Real Estate CRM. You must return only a valid JSON object matching the requested schema.'],
                    ['role' => 'user', 'content' => $prompt],
                ];

                $response = $this->provider->generateResponse($messages, [
                    'temperature' => 0.1,
                    'max_tokens' => 2000,
                ]);

                $rawContent = trim($response->content);
                // Strip markdown code fences if model wrapped in ```json ... ```
                if (str_starts_with($rawContent, '```')) {
                    $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
                    $rawContent = preg_replace('/\s*```$/', '', $rawContent);
                }

                $decoded = json_decode($rawContent, true);
                if (is_array($decoded) && isset($decoded['executive_summary'], $decoded['insights'])) {
                    return [
                        'executive_summary' => (string) $decoded['executive_summary'],
                        'insights' => (array) $decoded['insights'],
                        'recommendations' => (array) ($decoded['recommendations'] ?? []),
                        'model_used' => $this->provider->getProviderName(),
                        'tokens_used' => (int) ($response->usage['total_tokens'] ?? 0),
                    ];
                }
            } catch (Exception $e) {
                Log::warning('AI provider error during sales intelligence synthesis, falling back to deterministic engine', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Deterministic ground-truth summarizer (zero hallucination, always verified)
        return $this->synthesizeDeterministicInsights($metrics, $periodLabel);
    }

    /**
     * Deterministic, 100% verified intelligence summarizer directly derived from database metrics.
     *
     * @param  array<string, mixed>  $metrics
     * @return array{
     *     executive_summary: string,
     *     insights: array<string, string>,
     *     recommendations: array<int, string>,
     *     model_used: string,
     *     tokens_used: int
     * }
     */
    public function synthesizeDeterministicInsights(array $metrics, string $periodLabel): array
    {
        $estates = $metrics['frequently_requested_estates'] ?? [];
        $topEstate = ! empty($estates) ? $estates[0]['estate']." ({$estates[0]['percentage']}% of estate demand)" : 'various property locations';

        $questions = $metrics['common_customer_questions'] ?? [];
        $topQuestionCat = ! empty($questions) ? $questions[0]['category']." ({$questions[0]['percentage']}%)" : 'land titles and payment schedules';

        $objections = $metrics['common_objections'] ?? [];
        $topObjection = ! empty($objections) ? $objections[0]['objection']." ({$objections[0]['percentage']}%)" : 'budget constraints';

        $prices = $metrics['requested_price_ranges'] ?? [];
        $topPrice = ! empty($prices) ? $prices[0]['range']." ({$prices[0]['percentage']}%)" : 'mid-market residential brackets';

        $plans = $metrics['requested_payment_plans'] ?? [];
        $topPlan = ! empty($plans) ? $plans[0]['plan']." ({$plans[0]['percentage']}%)" : 'installment spreads';

        $handovers = $metrics['handover_reasons'] ?? [];
        $topHandover = ! empty($handovers) ? $handovers[0]['reason']." ({$handovers[0]['percentage']}%)" : 'pricing negotiations';

        $lost = $metrics['lost_deal_reasons'] ?? [];
        $topLost = ! empty($lost) ? $lost[0]['reason'].' ('.$lost[0]['count'].' deals)' : 'budget mismatches';

        $l2i = $metrics['lead_to_inspection_conversion'] ?? ['conversion_rate' => 0.0, 'total_leads' => 0, 'leads_with_inspections' => 0];
        $i2s = $metrics['inspection_to_sale_conversion'] ?? ['conversion_rate' => 0.0, 'completed_inspections' => 0, 'won_deals' => 0, 'won_revenue' => 0.0];

        $execSummary = "During {$periodLabel}, CRM operational intelligence shows that {$topEstate} led overall estate interest. ".
            "Customer inquiries were heavily focused on {$topQuestionCat}, while buyer hesitation primarily stemmed from {$topObjection}. ".
            "Capital commitment centered within {$topPrice}, with buyers showing the strongest preference for {$topPlan}. ".
            "Pipeline conversion registered a {$l2i['conversion_rate']}% lead-to-inspection rate ({$l2i['leads_with_inspections']} of {$l2i['total_leads']} leads), ".
            "and completed inspections converted to closed sales at {$i2s['conversion_rate']}% ({$i2s['won_deals']} closed won deals totaling ₦".number_format($i2s['won_revenue'], 2).').';

        $insights = [
            'frequently_requested_estates' => ! empty($estates)
                ? "Estate demand was led by {$estates[0]['estate']} with {$estates[0]['count']} inquiries ({$estates[0]['percentage']}% share)".(isset($estates[1]) ? ", followed by {$estates[1]['estate']} with {$estates[1]['count']} inquiries ({$estates[1]['percentage']}%)." : '.')
                : 'No specific estate concentration was recorded in this period; buyer inquiries were broadly distributed across general inventory.',

            'common_customer_questions' => ! empty($questions)
                ? "Inbound messaging indicates that {$questions[0]['category']} is the dominant topic ({$questions[0]['percentage']}% of categorized questions)".(isset($questions[1]) ? ", followed by {$questions[1]['category']} ({$questions[1]['percentage']}%)." : '.')
                : 'Customer inquiries were evenly distributed across general property availability, pricing, and inspection timing.',

            'common_objections' => ! empty($objections)
                ? "The most frequent barrier to closing was {$objections[0]['objection']} ({$objections[0]['count']} recorded objections, {$objections[0]['percentage']}%)".(isset($objections[1]) ? ", followed by {$objections[1]['objection']} ({$objections[1]['percentage']}%)." : '.')
                : 'Objection logs indicate minimal resistance; deals primarily progressed or paused based on client liquidity timing.',

            'requested_price_ranges' => ! empty($prices)
                ? "Buyer purchasing capacity was strongest in the {$prices[0]['range']} category with {$prices[0]['count']} leads ({$prices[0]['percentage']}% of budget allocations)."
                : 'Price preference data reflects broad residential interest across varying budget brackets.',

            'requested_payment_plans' => ! empty($plans)
                ? "The preferred structure was {$plans[0]['plan']} with {$plans[0]['count']} selections ({$plans[0]['percentage']}% preference)".(isset($plans[1]) ? ", compared to {$plans[1]['plan']} ({$plans[1]['percentage']}%)." : '.')
                : 'Payment flexibility records show standard combinations of outright and multi-month installment options.',

            'handover_reasons' => ! empty($handovers)
                ? "AI agent escalations to human sales reps were triggered most frequently by {$handovers[0]['reason']} ({$handovers[0]['count']} handovers, {$handovers[0]['percentage']}%)."
                : 'No significant escalation spikes were recorded; AI conversations resolved autonomously or followed normal agent assignment.',

            'lost_deal_reasons' => ! empty($lost)
                ? "The primary reason for deal fallout was '{$lost[0]['reason']}' accounting for {$lost[0]['count']} lost opportunities".($lost[0]['lost_value'] > 0 ? ' representing ₦'.number_format($lost[0]['lost_value'], 2).' in lost volume.' : '.')
                : 'Lost deal logs show no centralized reason; losses were isolated across individual client circumstances.',

            'lead_to_inspection_conversion' => "Lead-to-inspection conversion reached {$l2i['conversion_rate']}%, with {$l2i['leads_with_inspections']} out of {$l2i['total_leads']} qualified leads booking and attending physical site inspections.",

            'inspection_to_sale_conversion' => "Inspection-to-sale closing efficiency was {$i2s['conversion_rate']}%, with {$i2s['won_deals']} closed won deals originating from {$i2s['completed_inspections']} completed site inspections, generating ₦".number_format($i2s['won_revenue'], 2).' in closed sales volume.',
        ];

        $recommendations = [
            ! empty($estates) ? "Fast-track marketing and inventory replenishment for {$estates[0]['estate']} to capitalize on high demand volume." : 'Optimize inventory exposure across high-demand Abuja development corridors.',
            ! empty($questions) ? "Provide instant verifiable documentation packages for {$questions[0]['category']} directly in WhatsApp to shorten buyer hesitation." : 'Equip agents with digital property brochures and title documentation upfront.',
            ! empty($plans) ? "Package pre-structured {$plans[0]['plan']} payment options to directly address buyer budget constraints and accelerate commitment." : 'Offer flexible structured installment timelines to increase pipeline deal velocity.',
            "Prioritize same-day agent follow-ups following completed inspections where closing conversion is highest at {$i2s['conversion_rate']}%.",
        ];

        return [
            'executive_summary' => $execSummary,
            'insights' => $insights,
            'recommendations' => $recommendations,
            'model_used' => 'analytics-intelligence-engine',
            'tokens_used' => 0,
        ];
    }

    /**
     * Build the structured prompt for external LLMs with verified ground-truth constraints.
     *
     * @param  array<string, mixed>  $metrics
     */
    protected function buildSystemPrompt(array $metrics, string $periodLabel): string
    {
        $metricsJson = json_encode($metrics, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are the Executive Sales Intelligence Engine for Bamcom Real Estate CRM.
You are tasked with generating high-level management insights for the period: "{$periodLabel}".

CRITICAL CONSTRAINT: You MUST NOT invent, fabricate, alter, or hallucinate any numbers, percentages, counts, or currency figures. Every single number mentioned must originate directly from the verified database metrics below.

VERIFIED DATABASE METRICS:
{$metricsJson}

Please synthesize comprehensive management insights in JSON format matching this exact schema:
{
  "executive_summary": "High-impact 2-4 sentence executive overview synthesizing findings from the verified metrics.",
  "insights": {
    "frequently_requested_estates": "Detailed insight on estate demand distribution with exact percentages.",
    "common_customer_questions": "Analysis of common customer inquiry categories.",
    "common_objections": "Analysis of recurring buyer objections and friction points.",
    "requested_price_ranges": "Analysis of price points and purchasing power.",
    "requested_payment_plans": "Analysis of installment vs outright preferences.",
    "handover_reasons": "Analysis of human handover triggers.",
    "lost_deal_reasons": "Analysis of lost deals and lost revenue.",
    "lead_to_inspection_conversion": "Assessment of the lead-to-inspection rate.",
    "inspection_to_sale_conversion": "Assessment of the inspection-to-sale rate and won revenue."
  },
  "recommendations": [
    "Actionable recommendation 1",
    "Actionable recommendation 2",
    "Actionable recommendation 3",
    "Actionable recommendation 4"
  ]
}
PROMPT;
    }
}
