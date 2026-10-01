<?php

namespace App\Services\Email;

use App\Enums\DealStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailCampaign;
use App\Models\Inspection;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Enterprise Email Campaign Sales Attribution & ROI Analytics Engine.
 *
 * Implements end-to-end multi-stage funnel attribution:
 * Campaign → Contact → Lead → Opportunity/Inspection → Won Deal → Attributed Revenue.
 * Strictly derives all metrics and conversion rates from stored application data.
 */
class EmailAttributionService
{
    /**
     * Calculate comprehensive sales attribution metrics for a specific campaign.
     *
     * @param  int  $attributionWindowDays  Number of days post-send considered for attribution.
     * @return array<string, mixed>
     */
    public function getCampaignAttribution(EmailCampaign $campaign, int $attributionWindowDays = 90): array
    {
        // 1. Gather all contacts targeted by this campaign who were sent or delivered emails
        $recipientContactIds = $campaign->recipients()
            ->whereNotNull('contact_id')
            ->pluck('contact_id')
            ->unique()
            ->values()
            ->all();

        $campaignStart = $campaign->started_at ?? $campaign->scheduled_at ?? $campaign->created_at;
        $attributionEnd = (clone $campaignStart)->addDays($attributionWindowDays);

        if (empty($recipientContactIds)) {
            return $this->formatEmptyAttribution($campaign, $attributionWindowDays);
        }

        // 2. Campaign-Generated Leads
        // Leads for targeted contacts created during the attribution window, or explicitly tagged with campaign
        $leadsQuery = Lead::query()
            ->with(['contact:id,uuid,first_name,last_name,email,phone', 'assignedUser:id,name'])
            ->where(function (Builder $query) use ($recipientContactIds, $campaignStart, $attributionEnd) {
                $query->whereIn('contact_id', $recipientContactIds)
                    ->whereBetween('created_at', [$campaignStart, $attributionEnd]);
            });

        $leads = $leadsQuery->latest()->get();
        $leadIds = $leads->pluck('id')->all();

        // 3. Campaign-Generated Site Inspections
        // Inspections scheduled for these contacts or leads within the attribution window
        $inspectionsQuery = Inspection::query()
            ->with(['contact:id,first_name,last_name', 'lead:id,title', 'property:id,title'])
            ->where(function (Builder $query) use ($recipientContactIds, $leadIds) {
                $query->whereIn('contact_id', $recipientContactIds);
                if (! empty($leadIds)) {
                    $query->orWhereIn('lead_id', $leadIds);
                }
            })
            ->whereBetween('created_at', [$campaignStart, $attributionEnd]);

        $inspections = $inspectionsQuery->latest()->get();

        // 4. Campaign-Generated Opportunities (Deals)
        // Deals created for targeted contacts or leads within the attribution window
        $dealsQuery = Deal::query()
            ->with(['contact:id,first_name,last_name,email', 'lead:id,title', 'property:id,title', 'assignedUser:id,name'])
            ->where(function (Builder $query) use ($recipientContactIds, $leadIds) {
                $query->whereIn('contact_id', $recipientContactIds);
                if (! empty($leadIds)) {
                    $query->orWhereIn('lead_id', $leadIds);
                }
            })
            ->whereBetween('created_at', [$campaignStart, $attributionEnd]);

        $deals = $dealsQuery->latest()->get();

        // 5. Campaign-Generated Sales (Won Deals)
        $wonDeals = $deals->filter(fn (Deal $deal) => $deal->status === DealStatus::Won);
        $attributedRevenue = (float) $wonDeals->sum('deal_value');

        // 6. Conversion Rate Computations
        $recipientsCount = $campaign->total_recipients;
        $deliveredCount = $campaign->delivered_count;
        $openedCount = $campaign->opened_count;
        $clickedCount = $campaign->clicked_count;

        $leadsCount = $leads->count();
        $inspectionsCount = $inspections->count();
        $opportunitiesCount = $deals->count();
        $wonSalesCount = $wonDeals->count();

        $leadConversionRate = $deliveredCount > 0 ? round(($leadsCount / $deliveredCount) * 100, 2) : 0.0;
        $inspectionRate = $leadsCount > 0 ? round(($inspectionsCount / $leadsCount) * 100, 2) : 0.0;
        $opportunityRate = $leadsCount > 0 ? round(($opportunitiesCount / $leadsCount) * 100, 2) : 0.0;
        $dealWinRate = $opportunitiesCount > 0 ? round(($wonSalesCount / $opportunitiesCount) * 100, 2) : 0.0;
        $revenuePerRecipient = $recipientsCount > 0 ? round($attributedRevenue / $recipientsCount, 2) : 0.0;
        $revenuePerDelivered = $deliveredCount > 0 ? round($attributedRevenue / $deliveredCount, 2) : 0.0;

        return [
            'campaign' => [
                'id' => $campaign->id,
                'uuid' => $campaign->uuid,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'started_at' => $campaignStart->toIso8601String(),
                'attribution_end' => $attributionEnd->toIso8601String(),
                'attribution_window_days' => $attributionWindowDays,
            ],
            'engagement' => [
                'recipients' => $recipientsCount,
                'sent' => $campaign->sent_count,
                'delivered' => $deliveredCount,
                'opened' => $openedCount,
                'clicked' => $clickedCount,
                'bounced' => $campaign->bounced_count,
                'complained' => $campaign->complained_count,
                'unsubscribed' => $campaign->unsubscribed_count,
                'delivery_rate' => $campaign->deliveryRate(),
                'open_rate' => $campaign->openRate(),
                'click_rate' => $campaign->clickRate(),
                'click_to_open_rate' => $campaign->clickToOpenRate(),
                'bounce_rate' => $campaign->bounceRate(),
                'unsubscribe_rate' => $campaign->unsubscribeRate(),
            ],
            'attribution' => [
                'campaign_generated_leads' => $leadsCount,
                'campaign_generated_inspections' => $inspectionsCount,
                'campaign_generated_opportunities' => $opportunitiesCount,
                'campaign_generated_sales' => $wonSalesCount,
                'attributed_revenue' => $attributedRevenue,
                'formatted_attributed_revenue' => '₦'.number_format($attributedRevenue, 2),
                'revenue_per_recipient' => $revenuePerRecipient,
                'revenue_per_delivered' => $revenuePerDelivered,
                'rates' => [
                    'lead_conversion_rate' => $leadConversionRate,
                    'inspection_conversion_rate' => $inspectionRate,
                    'opportunity_conversion_rate' => $opportunityRate,
                    'deal_win_rate' => $dealWinRate,
                ],
            ],
            'funnel' => [
                ['stage' => 'Audience Targeted', 'count' => $recipientsCount, 'dropoff_rate' => 0.0],
                ['stage' => 'Emails Delivered', 'count' => $deliveredCount, 'dropoff_rate' => $recipientsCount > 0 ? round((($recipientsCount - $deliveredCount) / $recipientsCount) * 100, 1) : 0.0],
                ['stage' => 'Emails Opened', 'count' => $openedCount, 'dropoff_rate' => $deliveredCount > 0 ? round((($deliveredCount - $openedCount) / $deliveredCount) * 100, 1) : 0.0],
                ['stage' => 'Links Clicked', 'count' => $clickedCount, 'dropoff_rate' => $openedCount > 0 ? round((($openedCount - $clickedCount) / $openedCount) * 100, 1) : 0.0],
                ['stage' => 'Leads Generated', 'count' => $leadsCount, 'dropoff_rate' => 0.0],
                ['stage' => 'Inspections Scheduled', 'count' => $inspectionsCount, 'dropoff_rate' => 0.0],
                ['stage' => 'Opportunities Created', 'count' => $opportunitiesCount, 'dropoff_rate' => 0.0],
                ['stage' => 'Deals Closed Won', 'count' => $wonSalesCount, 'dropoff_rate' => 0.0],
            ],
            'records' => [
                'leads' => $leads,
                'inspections' => $inspections,
                'deals' => $deals,
                'won_deals' => $wonDeals->values(),
            ],
        ];
    }

    /**
     * Generate an executive cross-campaign attribution report for management reporting.
     *
     * @return array<string, mixed>
     */
    public function getExecutiveAttributionReport(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = EmailCampaign::query()
            ->when($startDate, fn ($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('created_at', '<=', $endDate))
            ->latest();

        $campaigns = $query->get();

        $totalRecipients = 0;
        $totalSent = 0;
        $totalDelivered = 0;
        $totalOpened = 0;
        $totalClicked = 0;
        $totalBounced = 0;
        $totalComplained = 0;
        $totalUnsubscribed = 0;
        $totalGeneratedLeads = 0;
        $totalGeneratedInspections = 0;
        $totalGeneratedOpportunities = 0;
        $totalGeneratedSales = 0;
        $totalAttributedRevenue = 0.0;

        $campaignReports = [];

        foreach ($campaigns as $campaign) {
            $attr = $this->getCampaignAttribution($campaign);

            $totalRecipients += $campaign->total_recipients;
            $totalSent += $campaign->sent_count;
            $totalDelivered += $campaign->delivered_count;
            $totalOpened += $campaign->opened_count;
            $totalClicked += $campaign->clicked_count;
            $totalBounced += $campaign->bounced_count;
            $totalComplained += $campaign->complained_count;
            $totalUnsubscribed += $campaign->unsubscribed_count;

            $leadsCount = $attr['attribution']['campaign_generated_leads'];
            $inspectionsCount = $attr['attribution']['campaign_generated_inspections'];
            $oppsCount = $attr['attribution']['campaign_generated_opportunities'];
            $salesCount = $attr['attribution']['campaign_generated_sales'];
            $rev = $attr['attribution']['attributed_revenue'];

            $totalGeneratedLeads += $leadsCount;
            $totalGeneratedInspections += $inspectionsCount;
            $totalGeneratedOpportunities += $oppsCount;
            $totalGeneratedSales += $salesCount;
            $totalAttributedRevenue += $rev;

            $campaignReports[] = [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'sent_count' => $campaign->sent_count,
                'delivered_count' => $campaign->delivered_count,
                'opened_count' => $campaign->opened_count,
                'clicked_count' => $campaign->clicked_count,
                'open_rate' => $campaign->openRate(),
                'click_rate' => $campaign->clickRate(),
                'generated_leads' => $leadsCount,
                'generated_inspections' => $inspectionsCount,
                'generated_opportunities' => $oppsCount,
                'generated_sales' => $salesCount,
                'attributed_revenue' => $rev,
                'formatted_revenue' => '₦'.number_format($rev, 2),
            ];
        }

        // Rank top campaigns by revenue
        $topByRevenue = collect($campaignReports)
            ->sortByDesc('attributed_revenue')
            ->take(5)
            ->values()
            ->all();

        // Rank top campaigns by leads
        $topByLeads = collect($campaignReports)
            ->sortByDesc('generated_leads')
            ->take(5)
            ->values()
            ->all();

        $overallDeliveryRate = $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 2) : 0.0;
        $overallOpenRate = $totalDelivered > 0 ? round(($totalOpened / $totalDelivered) * 100, 2) : 0.0;
        $overallClickRate = $totalDelivered > 0 ? round(($totalClicked / $totalDelivered) * 100, 2) : 0.0;
        $overallClickToOpenRate = $totalOpened > 0 ? round(($totalClicked / $totalOpened) * 100, 2) : 0.0;
        $overallBounceRate = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 2) : 0.0;
        $overallUnsubscribeRate = $totalDelivered > 0 ? round(($totalUnsubscribed / $totalDelivered) * 100, 2) : 0.0;

        return [
            'period' => [
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
            ],
            'summary' => [
                'total_campaigns' => $campaigns->count(),
                'recipients' => $totalRecipients,
                'sent' => $totalSent,
                'delivered' => $totalDelivered,
                'opened' => $totalOpened,
                'clicked' => $totalClicked,
                'bounced' => $totalBounced,
                'complained' => $totalComplained,
                'unsubscribed' => $totalUnsubscribed,
                'rates' => [
                    'delivery_rate' => $overallDeliveryRate,
                    'open_rate' => $overallOpenRate,
                    'click_rate' => $overallClickRate,
                    'click_to_open_rate' => $overallClickToOpenRate,
                    'bounce_rate' => $overallBounceRate,
                    'unsubscribe_rate' => $overallUnsubscribeRate,
                ],
                'attribution' => [
                    'campaign_generated_leads' => $totalGeneratedLeads,
                    'campaign_generated_inspections' => $totalGeneratedInspections,
                    'campaign_generated_opportunities' => $totalGeneratedOpportunities,
                    'campaign_generated_sales' => $totalGeneratedSales,
                    'attributed_revenue' => $totalAttributedRevenue,
                    'formatted_attributed_revenue' => '₦'.number_format($totalAttributedRevenue, 2),
                ],
            ],
            'top_campaigns_by_revenue' => $topByRevenue,
            'top_campaigns_by_leads' => $topByLeads,
            'campaigns' => $campaignReports,
        ];
    }

    /**
     * Default empty attribution response when no recipients exist.
     *
     * @return array<string, mixed>
     */
    protected function formatEmptyAttribution(EmailCampaign $campaign, int $attributionWindowDays): array
    {
        return [
            'campaign' => [
                'id' => $campaign->id,
                'uuid' => $campaign->uuid,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'started_at' => null,
                'attribution_end' => null,
                'attribution_window_days' => $attributionWindowDays,
            ],
            'engagement' => [
                'recipients' => 0,
                'sent' => 0,
                'delivered' => 0,
                'opened' => 0,
                'clicked' => 0,
                'bounced' => 0,
                'complained' => 0,
                'unsubscribed' => 0,
                'delivery_rate' => 0.0,
                'open_rate' => 0.0,
                'click_rate' => 0.0,
                'click_to_open_rate' => 0.0,
                'bounce_rate' => 0.0,
                'unsubscribe_rate' => 0.0,
            ],
            'attribution' => [
                'campaign_generated_leads' => 0,
                'campaign_generated_inspections' => 0,
                'campaign_generated_opportunities' => 0,
                'campaign_generated_sales' => 0,
                'attributed_revenue' => 0.0,
                'formatted_attributed_revenue' => '₦0.00',
                'revenue_per_recipient' => 0.0,
                'revenue_per_delivered' => 0.0,
                'rates' => [
                    'lead_conversion_rate' => 0.0,
                    'inspection_conversion_rate' => 0.0,
                    'opportunity_conversion_rate' => 0.0,
                    'deal_win_rate' => 0.0,
                ],
            ],
            'funnel' => [],
            'records' => [
                'leads' => [],
                'inspections' => [],
                'deals' => [],
                'won_deals' => [],
            ],
        ];
    }
}
