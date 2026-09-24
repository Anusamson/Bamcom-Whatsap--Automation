<?php

namespace App\Services\AI;

use App\Enums\DealStatus;
use App\Enums\InspectionStatus;
use App\Enums\LeadStatus;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\Estate;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Message;
use Illuminate\Support\Carbon;

/**
 * Extracts and aggregates verified ground-truth CRM metrics strictly from database queries.
 * Prevents AI hallucinations and metric fabrications by computing exact figures first.
 */
class SalesIntelligenceMetricsExtractor
{
    /**
     * Extract all 9 verified intelligence datasets from live CRM tables for the given period.
     *
     * @return array<string, mixed>
     */
    public function extractMetrics(Carbon $start, Carbon $end): array
    {
        return [
            'frequently_requested_estates' => $this->extractFrequentlyRequestedEstates($start, $end),
            'common_customer_questions' => $this->extractCommonCustomerQuestions($start, $end),
            'common_objections' => $this->extractCommonObjections($start, $end),
            'requested_price_ranges' => $this->extractRequestedPriceRanges($start, $end),
            'requested_payment_plans' => $this->extractRequestedPaymentPlans($start, $end),
            'handover_reasons' => $this->extractHandoverReasons($start, $end),
            'lost_deal_reasons' => $this->extractLostDealReasons($start, $end),
            'lead_to_inspection_conversion' => $this->extractLeadToInspectionConversion($start, $end),
            'inspection_to_sale_conversion' => $this->extractInspectionToSaleConversion($start, $end),
        ];
    }

    /**
     * 1. Frequently requested estates from leads, properties, and customer messages.
     *
     * @return array<int, array{estate: string, count: int, percentage: float}>
     */
    protected function extractFrequentlyRequestedEstates(Carbon $start, Carbon $end): array
    {
        $estates = Estate::all(['id', 'name']);
        if ($estates->isEmpty()) {
            return [];
        }

        $results = [];
        $totalMentions = 0;

        foreach ($estates as $estate) {
            $name = trim($estate->name);

            // Leads directly tied via property belonging to this estate
            $propertyLeadsCount = Lead::whereBetween('created_at', [$start, $end])
                ->whereHas('property', fn ($pq) => $pq->where('estate_id', $estate->id))
                ->count();

            // Leads referencing the estate by name in title, location, interest, or notes
            $textLeadsCount = Lead::whereBetween('created_at', [$start, $end])
                ->where(function ($q) use ($name): void {
                    $q->where('title', 'like', "%{$name}%")
                        ->orWhere('preferred_location', 'like', "%{$name}%")
                        ->orWhere('property_interest', 'like', "%{$name}%")
                        ->orWhere('notes', 'like', "%{$name}%");
                })
                ->whereDoesntHave('property', fn ($pq) => $pq->where('estate_id', $estate->id))
                ->count();

            // Inbound messages asking about this estate
            $messagesCount = Message::inbound()
                ->whereBetween('created_at', [$start, $end])
                ->where('body', 'like', "%{$name}%")
                ->count();

            $total = $propertyLeadsCount + $textLeadsCount + $messagesCount;
            if ($total > 0) {
                $results[] = [
                    'estate' => $name,
                    'count' => $total,
                ];
                $totalMentions += $total;
            }
        }

        // Sort descending by count
        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        $divisor = max(1, $totalMentions);
        foreach ($results as &$item) {
            $item['percentage'] = round(($item['count'] / $divisor) * 100, 1);
        }

        return $results;
    }

    /**
     * 2. Common customer questions categorized from inbound WhatsApp messages.
     *
     * @return array<int, array{category: string, count: int, percentage: float, sample_keywords: string}>
     */
    protected function extractCommonCustomerQuestions(Carbon $start, Carbon $end): array
    {
        $inboundMessages = Message::inbound()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('body')
            ->pluck('body');

        if ($inboundMessages->isEmpty()) {
            return [];
        }

        $categories = [
            'Land Title & Legal Documentation' => [
                'patterns' => ['title', 'c of o', 'certificate', 'governor', 'gazette', 'deed', 'survey', 'legal', 'doc'],
                'count' => 0,
            ],
            'Price, Payment Plan & Installments' => [
                'patterns' => ['price', 'cost', 'how much', 'installment', 'payment plan', 'deposit', 'initial', 'spread', 'monthly'],
                'count' => 0,
            ],
            'Site Inspection & Physical Visit' => [
                'patterns' => ['inspection', 'visit', 'viewing', 'see the land', 'schedule visit', 'take me to the site', 'site visit'],
                'count' => 0,
            ],
            'Location, Access Road & Landmarks' => [
                'patterns' => ['location', 'where', 'access', 'road', 'landmark', 'near', 'distance', 'airport', 'expressway'],
                'count' => 0,
            ],
            'Infrastructure & Development Status' => [
                'patterns' => ['electricity', 'light', 'water', 'fence', 'security', 'drainage', 'development', 'building', 'ready'],
                'count' => 0,
            ],
            'Plot Size & Availability' => [
                'patterns' => ['size', 'sqm', 'dimension', 'available', 'units left', 'plot number', 'allocation'],
                'count' => 0,
            ],
        ];

        $matchedCount = 0;

        foreach ($inboundMessages as $body) {
            $lower = strtolower((string) $body);
            foreach ($categories as $catName => &$catData) {
                foreach ($catData['patterns'] as $pattern) {
                    if (str_contains($lower, $pattern)) {
                        $catData['count']++;
                        $matchedCount++;
                        break;
                    }
                }
            }
        }

        $results = [];
        $divisor = max(1, $matchedCount);

        foreach ($categories as $name => $data) {
            if ($data['count'] > 0) {
                $results[] = [
                    'category' => $name,
                    'count' => $data['count'],
                    'percentage' => round(($data['count'] / $divisor) * 100, 1),
                    'sample_keywords' => implode(', ', array_slice($data['patterns'], 0, 4)),
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * 3. Common buyer objections from lost deals, notes, and messages.
     *
     * @return array<int, array{objection: string, count: int, percentage: float}>
     */
    protected function extractCommonObjections(Carbon $start, Carbon $end): array
    {
        $objectionCategories = [
            'Budget / Price Resistance' => ['expensive', 'too high', 'cannot afford', 'budget', 'discount', 'reduction', 'cost too much'],
            'Location & Distance Concerns' => ['too far', 'distance', 'bad road', 'remote', 'far from town', 'not central'],
            'Payment Flexibility & Spread' => ['payment plan too short', 'deposit too high', 'need longer spread', 'cash flow', 'installments'],
            'Title & Documentation Doubts' => ['verify title', 'c of o pending', 'family land', 'scam concern', 'documentation delay'],
            'Decision Maker Hesitation' => ['consult spouse', 'partner disagreed', 'need more time', 'delayed decision', 'postpone'],
        ];

        $counts = array_fill_keys(array_keys($objectionCategories), 0);
        $totalFound = 0;

        // 1. Lost reasons on Deals
        $lostDeals = Deal::where('status', DealStatus::Lost)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('lost_reason')
            ->pluck('lost_reason');

        // 2. Lost reasons on Leads
        $lostLeads = Lead::whereNotNull('lost_reason')
            ->whereBetween('created_at', [$start, $end])
            ->pluck('lost_reason');

        // 3. Inbound messages with objection signals
        $messages = Message::inbound()
            ->whereBetween('created_at', [$start, $end])
            ->pluck('body');

        $textCorpus = $lostDeals->concat($lostLeads)->concat($messages);

        foreach ($textCorpus as $text) {
            $lower = strtolower((string) $text);
            foreach ($objectionCategories as $catName => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($lower, $kw)) {
                        $counts[$catName]++;
                        $totalFound++;
                        break;
                    }
                }
            }
        }

        $results = [];
        $divisor = max(1, $totalFound);

        foreach ($counts as $name => $cnt) {
            if ($cnt > 0) {
                $results[] = [
                    'objection' => $name,
                    'count' => $cnt,
                    'percentage' => round(($cnt / $divisor) * 100, 1),
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * 4. Requested price ranges based on lead budgets and deal values.
     *
     * @return array<int, array{range: string, count: int, percentage: float}>
     */
    protected function extractRequestedPriceRanges(Carbon $start, Carbon $end): array
    {
        $ranges = [
            'Under ₦10M (Starter / Affordable)' => ['min' => 0, 'max' => 10000000, 'count' => 0],
            '₦10M - ₦25M (Mid-Tier Residential)' => ['min' => 10000000, 'max' => 25000000, 'count' => 0],
            '₦25M - ₦50M (Prime Residential Plots)' => ['min' => 25000000, 'max' => 50000000, 'count' => 0],
            '₦50M - ₦100M (Commercial / Executive)' => ['min' => 50000000, 'max' => 100000000, 'count' => 0],
            'Above ₦100M (HNW / Multi-Plot)' => ['min' => 100000000, 'max' => PHP_FLOAT_MAX, 'count' => 0],
        ];

        $leads = Lead::whereBetween('created_at', [$start, $end])
            ->where(function ($q): void {
                $q->whereNotNull('budget_min')->orWhereNotNull('budget_max');
            })
            ->get(['budget_min', 'budget_max']);

        $deals = Deal::whereBetween('created_at', [$start, $end])
            ->where('deal_value', '>', 0)
            ->pluck('deal_value');

        $totalDataPoints = 0;

        foreach ($leads as $lead) {
            $val = (float) ($lead->budget_max ?: $lead->budget_min ?: 0);
            if ($val > 0) {
                foreach ($ranges as &$tier) {
                    if ($val >= $tier['min'] && $val < $tier['max']) {
                        $tier['count']++;
                        $totalDataPoints++;
                        break;
                    }
                }
            }
        }

        foreach ($deals as $dealVal) {
            $val = (float) $dealVal;
            foreach ($ranges as &$tier) {
                if ($val >= $tier['min'] && $val < $tier['max']) {
                    $tier['count']++;
                    $totalDataPoints++;
                    break;
                }
            }
        }

        $results = [];
        $divisor = max(1, $totalDataPoints);

        foreach ($ranges as $label => $data) {
            if ($data['count'] > 0) {
                $results[] = [
                    'range' => $label,
                    'count' => $data['count'],
                    'percentage' => round(($data['count'] / $divisor) * 100, 1),
                ];
            }
        }

        return $results;
    }

    /**
     * 5. Requested payment plans from customer notes and messages.
     *
     * @return array<int, array{plan: string, count: int, percentage: float}>
     */
    protected function extractRequestedPaymentPlans(Carbon $start, Carbon $end): array
    {
        $planPatterns = [
            'Outright Payment (0-30 Days)' => ['outright', 'full payment', 'one-off', 'once', 'pay full'],
            '3-6 Months Spread' => ['3 months', '3 month', '6 months', '6 month', 'quarterly', 'half year'],
            '12 Months Installments' => ['12 months', '12 month', '1 year', 'one year', 'twelve months'],
            '18-24 Months Extended Plan' => ['18 months', '24 months', '2 years', 'two years', 'flexible plan', 'extended spread'],
        ];

        $counts = array_fill_keys(array_keys($planPatterns), 0);
        $totalMatched = 0;

        $texts = Message::inbound()->whereBetween('created_at', [$start, $end])->pluck('body')
            ->concat(Lead::whereBetween('created_at', [$start, $end])->pluck('notes'))
            ->concat(Deal::whereBetween('created_at', [$start, $end])->pluck('notes'));

        foreach ($texts as $text) {
            $lower = strtolower((string) $text);
            foreach ($planPatterns as $planName => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($lower, $kw)) {
                        $counts[$planName]++;
                        $totalMatched++;
                        break;
                    }
                }
            }
        }

        $results = [];
        $divisor = max(1, $totalMatched);

        foreach ($counts as $planName => $cnt) {
            if ($cnt > 0) {
                $results[] = [
                    'plan' => $planName,
                    'count' => $cnt,
                    'percentage' => round(($cnt / $divisor) * 100, 1),
                ];
            }
        }

        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * 6. Human Handover reasons from Activity records.
     *
     * @return array<int, array{reason: string, count: int, percentage: float}>
     */
    protected function extractHandoverReasons(Carbon $start, Carbon $end): array
    {
        $activities = Activity::whereIn('activity_type', ['ai_handover_executed', 'human_handover_requested'])
            ->whereBetween('created_at', [$start, $end])
            ->get(['properties']);

        if ($activities->isEmpty()) {
            return [];
        }

        $counts = [];
        $total = $activities->count();

        foreach ($activities as $act) {
            $props = (array) ($act->properties ?? []);
            $reason = (string) ($props['trigger_label'] ?? $props['trigger'] ?? 'Customer Explicit Request');
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        arsort($counts);

        $results = [];
        foreach ($counts as $reason => $cnt) {
            $results[] = [
                'reason' => $reason,
                'count' => $cnt,
                'percentage' => round(($cnt / $total) * 100, 1),
            ];
        }

        return $results;
    }

    /**
     * 7. Lost deal reasons from Deals and Leads.
     *
     * @return array<int, array{reason: string, count: int, lost_value: float}>
     */
    protected function extractLostDealReasons(Carbon $start, Carbon $end): array
    {
        $lostDeals = Deal::where('status', DealStatus::Lost)
            ->whereBetween('created_at', [$start, $end])
            ->get(['lost_reason', 'deal_value']);

        $groups = [];

        foreach ($lostDeals as $deal) {
            $reason = trim((string) $deal->lost_reason) ?: 'Unspecified lost reason';
            if (! isset($groups[$reason])) {
                $groups[$reason] = ['count' => 0, 'lost_value' => 0.0];
            }
            $groups[$reason]['count']++;
            $groups[$reason]['lost_value'] += (float) $deal->deal_value;
        }

        // Also check lost leads
        $lostLeads = Lead::where('status', LeadStatus::Lost)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('lost_reason')
            ->pluck('lost_reason');

        foreach ($lostLeads as $reason) {
            $cleanReason = trim((string) $reason) ?: 'Unspecified lost reason';
            if (! isset($groups[$cleanReason])) {
                $groups[$cleanReason] = ['count' => 0, 'lost_value' => 0.0];
            }
            $groups[$cleanReason]['count']++;
        }

        $results = [];
        foreach ($groups as $reason => $data) {
            $results[] = [
                'reason' => $reason,
                'count' => $data['count'],
                'lost_value' => $data['lost_value'],
            ];
        }

        usort($results, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $results;
    }

    /**
     * 8. Lead-to-Inspection conversion metrics.
     *
     * @return array{total_leads: int, leads_with_inspections: int, conversion_rate: float}
     */
    protected function extractLeadToInspectionConversion(Carbon $start, Carbon $end): array
    {
        $totalLeads = Lead::whereBetween('created_at', [$start, $end])->count();

        // Leads with at least 1 inspection on their associated contact
        $leadsWithInspections = Lead::whereBetween('created_at', [$start, $end])
            ->whereHas('contact.inspections', function ($iq) use ($start, $end): void {
                $iq->whereBetween('created_at', [$start, $end]);
            })
            ->count();

        $conversionRate = $totalLeads > 0
            ? round(($leadsWithInspections / $totalLeads) * 100, 1)
            : 0.0;

        return [
            'total_leads' => $totalLeads,
            'leads_with_inspections' => $leadsWithInspections,
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * 9. Inspection-to-Sale conversion metrics.
     *
     * @return array{completed_inspections: int, won_deals: int, conversion_rate: float, won_revenue: float}
     */
    protected function extractInspectionToSaleConversion(Carbon $start, Carbon $end): array
    {
        $completedInspections = Inspection::where('status', InspectionStatus::Completed)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Won deals tied to a contact who had a completed inspection
        $wonDealsQuery = Deal::where('status', DealStatus::Won)
            ->whereHas('contact.inspections', function ($iq) use ($start, $end): void {
                $iq->where('status', InspectionStatus::Completed)
                    ->whereBetween('created_at', [$start, $end]);
            });

        $wonDealsCount = (clone $wonDealsQuery)->count();
        $wonRevenue = (float) (clone $wonDealsQuery)->sum('deal_value');

        $conversionRate = $completedInspections > 0
            ? round(($wonDealsCount / $completedInspections) * 100, 1)
            : 0.0;

        return [
            'completed_inspections' => $completedInspections,
            'won_deals' => $wonDealsCount,
            'conversion_rate' => $conversionRate,
            'won_revenue' => $wonRevenue,
        ];
    }
}
