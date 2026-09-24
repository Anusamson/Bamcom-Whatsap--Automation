<?php

namespace App\Services\Analytics;

use App\Enums\ConversationMode;
use App\Enums\ConversationStatus;
use App\Enums\DealStatus;
use App\Enums\InspectionStatus;
use App\Enums\LeadTemperature;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for computing real-time CRM analytics, KPIs, and reports from database queries.
 */
class AnalyticsService
{
    /**
     * Resolve date range from request parameters with comparison period.
     *
     * @return array{
     *     start: Carbon,
     *     end: Carbon,
     *     previous_start: Carbon,
     *     previous_end: Carbon,
     *     period: string,
     *     label: string,
     *     date_from: string,
     *     date_to: string
     * }
     */
    public function resolveDateRange(Request $request): array
    {
        $period = $request->input('period', '30d');
        $now = now();

        switch ($period) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $prevStart = $start->copy()->subDay();
                $prevEnd = $end->copy()->subDay();
                $label = 'Today';
                break;

            case '7d':
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
                $diffDays = $start->diffInDays($end) + 1;
                $prevStart = $start->copy()->subDays($diffDays);
                $prevEnd = $start->copy()->subSecond();
                $label = 'Last 7 Days';
                break;

            case '90d':
                $start = $now->copy()->subDays(89)->startOfDay();
                $end = $now->copy()->endOfDay();
                $diffDays = $start->diffInDays($end) + 1;
                $prevStart = $start->copy()->subDays($diffDays);
                $prevEnd = $start->copy()->subSecond();
                $label = 'Last 90 Days';
                break;

            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $prevStart = $start->copy()->subMonth()->startOfMonth();
                $prevEnd = $start->copy()->subMonth()->endOfMonth();
                $label = 'This Month';
                break;

            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $prevStart = $start->copy()->subYear()->startOfYear();
                $prevEnd = $start->copy()->subYear()->endOfYear();
                $label = 'This Year';
                break;

            case 'custom':
                $dateFrom = $request->input('date_from');
                $dateTo = $request->input('date_to');

                $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $now->copy()->subDays(29)->startOfDay();
                $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : $now->copy()->endOfDay();
                $diffDays = max(1, $start->diffInDays($end) + 1);
                $prevStart = $start->copy()->subDays($diffDays);
                $prevEnd = $start->copy()->subSecond();
                $label = "{$start->format('M j, Y')} - {$end->format('M j, Y')}";
                break;

            case '30d':
            default:
                $period = '30d';
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $now->copy()->endOfDay();
                $diffDays = $start->diffInDays($end) + 1;
                $prevStart = $start->copy()->subDays($diffDays);
                $prevEnd = $start->copy()->subSecond();
                $label = 'Last 30 Days';
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'previous_start' => $prevStart,
            'previous_end' => $prevEnd,
            'period' => $period,
            'label' => $label,
            'date_from' => $start->toDateString(),
            'date_to' => $end->toDateString(),
        ];
    }

    /**
     * Compute all Dashboard KPIs strictly from database queries.
     *
     * @return array<string, mixed>
     */
    public function getDashboardKpis(Carbon $start, Carbon $end, ?Carbon $prevStart = null, ?Carbon $prevEnd = null): array
    {
        // 1. New Leads
        $newLeads = Lead::whereBetween('created_at', [$start, $end])->count();
        $prevNewLeads = $prevStart && $prevEnd ? Lead::whereBetween('created_at', [$prevStart, $prevEnd])->count() : 0;

        // 2. Hot Leads
        $hotLeads = Lead::where('temperature', LeadTemperature::Hot)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $prevHotLeads = $prevStart && $prevEnd ? Lead::where('temperature', LeadTemperature::Hot)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count() : 0;

        // 3. Active Conversations (open or pending in period)
        $activeConversations = Conversation::whereIn('status', [ConversationStatus::Open, ConversationStatus::Pending])
            ->whereBetween('updated_at', [$start, $end])
            ->count();
        $prevActiveConversations = $prevStart && $prevEnd ? Conversation::whereIn('status', [ConversationStatus::Open, ConversationStatus::Pending])
            ->whereBetween('updated_at', [$prevStart, $prevEnd])
            ->count() : 0;

        // 4. Inspections
        $inspectionsCount = Inspection::whereBetween('created_at', [$start, $end])->count();
        $prevInspections = $prevStart && $prevEnd ? Inspection::whereBetween('created_at', [$prevStart, $prevEnd])->count() : 0;

        // 5. Open Deals
        $openDeals = Deal::where('status', DealStatus::Open)
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $prevOpenDeals = $prevStart && $prevEnd ? Deal::where('status', DealStatus::Open)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count() : 0;

        // 6. Pipeline Value (sum of open deals)
        $pipelineValue = (float) Deal::where('status', DealStatus::Open)
            ->whereBetween('created_at', [$start, $end])
            ->sum('deal_value');
        $prevPipelineValue = $prevStart && $prevEnd ? (float) Deal::where('status', DealStatus::Open)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->sum('deal_value') : 0.0;

        // 7. Sales Won (sum and count of closed won deals)
        $wonDealsQuery = Deal::where('status', DealStatus::Won)
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('actual_close_date', [$start, $end])
                    ->orWhere(fn ($sq) => $sq->whereNull('actual_close_date')->whereBetween('updated_at', [$start, $end]));
            });

        $salesWon = (float) (clone $wonDealsQuery)->sum('deal_value');
        $salesWonCount = (int) (clone $wonDealsQuery)->count();

        $prevSalesWon = 0.0;
        if ($prevStart && $prevEnd) {
            $prevSalesWon = (float) Deal::where('status', DealStatus::Won)
                ->where(function ($q) use ($prevStart, $prevEnd): void {
                    $q->whereBetween('actual_close_date', [$prevStart, $prevEnd])
                        ->orWhere(fn ($sq) => $sq->whereNull('actual_close_date')->whereBetween('updated_at', [$prevStart, $prevEnd]));
                })->sum('deal_value');
        }

        // 8. Conversion Rate: Won Deals / Total Closed or Created Deals in period
        $totalDealsInPeriod = Deal::whereBetween('created_at', [$start, $end])->count();
        $conversionRate = $totalDealsInPeriod > 0
            ? round(($salesWonCount / $totalDealsInPeriod) * 100, 1)
            : ($newLeads > 0 ? round(($salesWonCount / $newLeads) * 100, 1) : 0.0);

        return [
            'new_leads' => [
                'value' => $newLeads,
                'previous' => $prevNewLeads,
                'change_percent' => $this->calculatePercentageChange($newLeads, $prevNewLeads),
            ],
            'hot_leads' => [
                'value' => $hotLeads,
                'previous' => $prevHotLeads,
                'change_percent' => $this->calculatePercentageChange($hotLeads, $prevHotLeads),
            ],
            'active_conversations' => [
                'value' => $activeConversations,
                'previous' => $prevActiveConversations,
                'change_percent' => $this->calculatePercentageChange($activeConversations, $prevActiveConversations),
            ],
            'inspections' => [
                'value' => $inspectionsCount,
                'previous' => $prevInspections,
                'change_percent' => $this->calculatePercentageChange($inspectionsCount, $prevInspections),
            ],
            'open_deals' => [
                'value' => $openDeals,
                'previous' => $prevOpenDeals,
                'change_percent' => $this->calculatePercentageChange($openDeals, $prevOpenDeals),
            ],
            'pipeline_value' => [
                'value' => $pipelineValue,
                'formatted' => '₦'.number_format($pipelineValue, 2),
                'previous' => $prevPipelineValue,
                'change_percent' => $this->calculatePercentageChange($pipelineValue, $prevPipelineValue),
            ],
            'sales_won' => [
                'value' => $salesWon,
                'count' => $salesWonCount,
                'formatted' => '₦'.number_format($salesWon, 2),
                'previous' => $prevSalesWon,
                'change_percent' => $this->calculatePercentageChange($salesWon, $prevSalesWon),
            ],
            'conversion_rate' => [
                'value' => $conversionRate,
                'formatted' => $conversionRate.'%',
            ],
        ];
    }

    /**
     * Report 1: Lead Source Breakdown
     *
     * @return array<string, mixed>
     */
    public function getLeadSourceReport(Carbon $start, Carbon $end): array
    {
        $sources = Lead::whereBetween('created_at', [$start, $end])
            ->select('lead_source', DB::raw('count(*) as total_leads'))
            ->groupBy('lead_source')
            ->orderByDesc('total_leads')
            ->get();

        $totalLeads = max(1, $sources->sum('total_leads'));

        $items = $sources->map(function ($row) use ($totalLeads, $start, $end): array {
            $sourceVal = $row->lead_source instanceof \BackedEnum ? $row->lead_source->value : (string) $row->lead_source;
            $count = (int) $row->total_leads;
            $percentage = round(($count / $totalLeads) * 100, 1);

            // Won revenue attributed to this source
            $wonRevenue = (float) Deal::whereHas('lead', function ($lq) use ($sourceVal): void {
                $lq->where('lead_source', $sourceVal);
            })->where('status', DealStatus::Won)
                ->whereBetween('created_at', [$start, $end])
                ->sum('deal_value');

            return [
                'source' => $sourceVal ?: 'Unknown',
                'label' => ucwords(str_replace('_', ' ', $sourceVal ?: 'Unknown')),
                'count' => $count,
                'percentage' => $percentage,
                'won_revenue' => $wonRevenue,
                'formatted_won_revenue' => '₦'.number_format($wonRevenue, 2),
            ];
        })->values()->all();

        return [
            'total_leads' => $totalLeads,
            'sources' => $items,
        ];
    }

    /**
     * Report 2: Pipeline Funnel Report across Stages
     *
     * @return array<string, mixed>
     */
    public function getPipelineFunnelReport(Carbon $start, Carbon $end): array
    {
        $stages = PipelineStage::query()
            ->orderBy('order_column')
            ->get(['id', 'name', 'color', 'order_column', 'probability', 'is_won', 'is_lost']);

        $funnel = [];
        $totalDeals = 0;

        foreach ($stages as $stage) {
            $leadsCount = Lead::where('pipeline_stage_id', $stage->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $dealsQuery = Deal::where('pipeline_stage_id', $stage->id)
                ->whereBetween('created_at', [$start, $end]);

            $dealsCount = (clone $dealsQuery)->count();
            $dealValue = (float) (clone $dealsQuery)->sum('deal_value');
            $totalDeals += $dealsCount;

            $funnel[] = [
                'stage_id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'order' => $stage->order_column,
                'probability' => $stage->probability,
                'is_won' => (bool) $stage->is_won,
                'is_lost' => (bool) $stage->is_lost,
                'leads_count' => $leadsCount,
                'deals_count' => $dealsCount,
                'total_value' => $dealValue,
                'formatted_value' => '₦'.number_format($dealValue, 2),
            ];
        }

        // Compute stage-to-stage conversion rate
        $firstCount = ! empty($funnel) ? max(1, $funnel[0]['deals_count'] + $funnel[0]['leads_count']) : 1;
        foreach ($funnel as $idx => &$step) {
            $stepVolume = $step['deals_count'] + $step['leads_count'];
            $step['conversion_rate'] = round(($stepVolume / $firstCount) * 100, 1);
        }

        return [
            'total_volume' => $totalDeals,
            'stages' => $funnel,
        ];
    }

    /**
     * Report 3: Sales Performance & Deal Velocity
     *
     * @return array<string, mixed>
     */
    public function getSalesPerformanceReport(Carbon $start, Carbon $end): array
    {
        $dealsQuery = Deal::whereBetween('created_at', [$start, $end]);

        $totalDeals = (clone $dealsQuery)->count();
        $wonDeals = (clone $dealsQuery)->where('status', DealStatus::Won)->count();
        $lostDeals = (clone $dealsQuery)->where('status', DealStatus::Lost)->count();
        $openDeals = (clone $dealsQuery)->where('status', DealStatus::Open)->count();

        $wonRevenue = (float) Deal::where('status', DealStatus::Won)
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween('actual_close_date', [$start, $end])
                    ->orWhere(fn ($sq) => $sq->whereNull('actual_close_date')->whereBetween('updated_at', [$start, $end]));
            })->sum('deal_value');

        $lostRevenue = (float) (clone $dealsQuery)->where('status', DealStatus::Lost)->sum('deal_value');

        $winRate = ($wonDeals + $lostDeals) > 0
            ? round(($wonDeals / ($wonDeals + $lostDeals)) * 100, 1)
            : ($totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0.0);

        $avgDealSize = $wonDeals > 0 ? round($wonRevenue / $wonDeals, 2) : 0.0;

        // Daily trend data points for chart
        $dailyTrends = Deal::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count'),
                DB::raw('sum(deal_value) as volume')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'count' => (int) $row->count,
                'volume' => (float) $row->volume,
            ])
            ->all();

        return [
            'total_deals' => $totalDeals,
            'won_deals' => $wonDeals,
            'lost_deals' => $lostDeals,
            'open_deals' => $openDeals,
            'won_revenue' => $wonRevenue,
            'formatted_won_revenue' => '₦'.number_format($wonRevenue, 2),
            'lost_revenue' => $lostRevenue,
            'formatted_lost_revenue' => '₦'.number_format($lostRevenue, 2),
            'win_rate' => $winRate,
            'avg_deal_size' => $avgDealSize,
            'formatted_avg_deal_size' => '₦'.number_format($avgDealSize, 2),
            'trends' => $dailyTrends,
        ];
    }

    /**
     * Report 4: Agent Performance Ranking & Quotas
     *
     * @return array<string, mixed>
     */
    public function getAgentPerformanceReport(Carbon $start, Carbon $end): array
    {
        $agents = User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $performance = [];

        foreach ($agents as $agent) {
            $leadsCount = Lead::where('assigned_user_id', $agent->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $completedInspections = Inspection::where('representative_id', $agent->id)
                ->where('status', InspectionStatus::Completed)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $dealsQuery = Deal::where('assigned_user_id', $agent->id)
                ->whereBetween('created_at', [$start, $end]);

            $openDeals = (clone $dealsQuery)->where('status', DealStatus::Open)->count();
            $wonDeals = (clone $dealsQuery)->where('status', DealStatus::Won)->count();
            $wonRevenue = (float) (clone $dealsQuery)->where('status', DealStatus::Won)->sum('deal_value');

            $winRate = ($wonDeals + $openDeals) > 0 ? round(($wonDeals / ($wonDeals + $openDeals)) * 100, 1) : 0.0;

            $performance[] = [
                'agent_id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'assigned_leads' => $leadsCount,
                'completed_inspections' => $completedInspections,
                'open_deals' => $openDeals,
                'won_deals' => $wonDeals,
                'won_revenue' => $wonRevenue,
                'formatted_won_revenue' => '₦'.number_format($wonRevenue, 2),
                'win_rate' => $winRate,
            ];
        }

        // Sort by won revenue desc
        usort($performance, fn ($a, $b) => $b['won_revenue'] <=> $a['won_revenue']);

        return [
            'agents' => $performance,
        ];
    }

    /**
     * Report 5: Inspection Conversion & Outcome Tracking
     *
     * @return array<string, mixed>
     */
    public function getInspectionConversionReport(Carbon $start, Carbon $end): array
    {
        $query = Inspection::whereBetween('created_at', [$start, $end]);

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', InspectionStatus::Completed)->count();
        $requested = (clone $query)->where('status', InspectionStatus::Requested)->count();
        $scheduled = (clone $query)->where('status', InspectionStatus::Scheduled)->count();
        $confirmed = (clone $query)->where('status', InspectionStatus::Confirmed)->count();
        $cancelled = (clone $query)->where('status', InspectionStatus::Cancelled)->count();
        $noShow = (clone $query)->where('status', InspectionStatus::NoShow)->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

        // Inspection to won deal conversion
        $convertedDealsCount = Deal::where('status', DealStatus::Won)
            ->whereHas('contact.inspections', function ($iq) use ($start, $end): void {
                $iq->where('status', InspectionStatus::Completed)
                    ->whereBetween('created_at', [$start, $end]);
            })->count();

        $dealConversionRate = $completed > 0 ? round(($convertedDealsCount / $completed) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'completed' => $completed,
            'requested' => $requested,
            'scheduled' => $scheduled,
            'confirmed' => $confirmed,
            'cancelled' => $cancelled,
            'no_show' => $noShow,
            'completion_rate' => $completionRate,
            'won_deals_from_inspection' => $convertedDealsCount,
            'inspection_to_deal_rate' => $dealConversionRate,
        ];
    }

    /**
     * Report 6: WhatsApp Campaign Performance
     *
     * @return array<string, mixed>
     */
    public function getCampaignPerformanceReport(Carbon $start, Carbon $end): array
    {
        $campaigns = Campaign::whereBetween('created_at', [$start, $end])
            ->latest()
            ->get();

        $totalCampaigns = $campaigns->count();
        $totalRecipients = (int) $campaigns->sum('total_recipients');
        $totalSent = (int) $campaigns->sum('sent_count');
        $totalDelivered = (int) $campaigns->sum('delivered_count');
        $totalRead = (int) $campaigns->sum('read_count');
        $totalFailed = (int) $campaigns->sum('failed_count');
        $totalOptedOut = (int) $campaigns->sum('opted_out_count');

        $deliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0.0;
        $readRate = $totalDelivered > 0 ? round(($totalRead / $totalDelivered) * 100, 1) : 0.0;
        $optOutRate = $totalSent > 0 ? round(($totalOptedOut / $totalSent) * 100, 2) : 0.0;

        $campaignList = $campaigns->take(10)->map(fn (Campaign $c): array => [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status->value,
            'total_recipients' => $c->total_recipients,
            'sent_count' => $c->sent_count,
            'delivered_count' => $c->delivered_count,
            'read_count' => $c->read_count,
            'failed_count' => $c->failed_count,
            'created_at' => $c->created_at?->toDateTimeString(),
        ])->all();

        return [
            'total_campaigns' => $totalCampaigns,
            'total_recipients' => $totalRecipients,
            'sent_count' => $totalSent,
            'delivered_count' => $totalDelivered,
            'read_count' => $totalRead,
            'failed_count' => $totalFailed,
            'opted_out_count' => $totalOptedOut,
            'delivery_rate' => $deliveryRate,
            'read_rate' => $readRate,
            'opt_out_rate' => $optOutRate,
            'campaigns' => $campaignList,
        ];
    }

    /**
     * Report 7: AI Conversations & Automation Metrics
     *
     * @return array<string, mixed>
     */
    public function getAiConversationsReport(Carbon $start, Carbon $end): array
    {
        $conversationsQuery = Conversation::whereBetween('created_at', [$start, $end]);

        $totalConversations = (clone $conversationsQuery)->count();
        $aiConversations = (clone $conversationsQuery)->where('mode', ConversationMode::Ai)->count();
        $hybridConversations = (clone $conversationsQuery)->where('mode', ConversationMode::Hybrid)->count();
        $humanConversations = (clone $conversationsQuery)->where('mode', ConversationMode::Human)->count();

        // Query AI Execution Logs
        $aiLogsQuery = DB::table('ai_execution_logs')->whereBetween('created_at', [$start, $end]);

        $totalAiExecutions = (clone $aiLogsQuery)->count();
        $totalTokens = (int) (clone $aiLogsQuery)->sum('total_tokens');
        $avgDuration = (float) (clone $aiLogsQuery)->avg('duration_ms');

        // AI resolution rate: AI conversations completed without handovers
        $aiHandoversInPeriod = Activity::where('activity_type', 'ai_handover_executed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $aiResolutionRate = $totalAiExecutions > 0
            ? max(0.0, round((($totalAiExecutions - $aiHandoversInPeriod) / max(1, $totalAiExecutions)) * 100, 1))
            : 100.0;

        return [
            'total_conversations' => $totalConversations,
            'ai_conversations' => $aiConversations,
            'hybrid_conversations' => $hybridConversations,
            'human_conversations' => $humanConversations,
            'total_ai_executions' => $totalAiExecutions,
            'total_tokens' => $totalTokens,
            'avg_duration_ms' => round($avgDuration, 2),
            'ai_resolution_rate' => $aiResolutionRate,
        ];
    }

    /**
     * Report 8: Human Handovers & Escalation Analytics
     *
     * @return array<string, mixed>
     */
    public function getHumanHandoversReport(Carbon $start, Carbon $end): array
    {
        $activities = Activity::whereIn('activity_type', ['ai_handover_executed', 'human_handover_requested'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();

        $totalHandovers = $activities->count();

        // Reason / Trigger breakdown
        $triggerCounts = [];
        $repCounts = [];

        foreach ($activities as $act) {
            $props = (array) ($act->properties ?? []);
            $trigger = (string) ($props['trigger_label'] ?? $props['trigger'] ?? 'Customer Request');
            $triggerCounts[$trigger] = ($triggerCounts[$trigger] ?? 0) + 1;

            $repName = (string) ($props['assigned_user_name'] ?? 'Unassigned');
            $repCounts[$repName] = ($repCounts[$repName] ?? 0) + 1;
        }

        arsort($triggerCounts);
        arsort($repCounts);

        $triggersList = [];
        foreach ($triggerCounts as $label => $cnt) {
            $triggersList[] = [
                'trigger' => $label,
                'count' => $cnt,
                'percentage' => $totalHandovers > 0 ? round(($cnt / $totalHandovers) * 100, 1) : 0,
            ];
        }

        $repsList = [];
        foreach ($repCounts as $name => $cnt) {
            $repsList[] = [
                'representative' => $name,
                'count' => $cnt,
            ];
        }

        return [
            'total_handovers' => $totalHandovers,
            'triggers' => $triggersList,
            'top_representatives' => array_slice($repsList, 0, 5),
        ];
    }

    /**
     * Helper to compute percentage change between two numerical values.
     */
    protected function calculatePercentageChange(float|int $current, float|int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
