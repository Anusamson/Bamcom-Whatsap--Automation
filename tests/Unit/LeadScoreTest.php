<?php

namespace Tests\Unit;

use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Models\Lead;
use App\Services\Lead\LeadService;
use PHPUnit\Framework\TestCase;

class LeadScoreTest extends TestCase
{
    protected LeadService $leadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->leadService = new LeadService;
    }

    public function test_baseline_score_with_default_cold_unqualified_flexible(): void
    {
        $lead = new Lead([
            'temperature' => LeadTemperature::Cold,
            'purchase_timeline' => PurchaseTimeline::Flexible,
            'qualification_status' => QualificationStatus::Unqualified,
            'budget_min' => null,
            'budget_max' => null,
        ]);

        // 20 (base) + 5 (cold) + 0 (flexible) + 0 (unqualified) + 0 (no budget) = 25
        $this->assertEquals(25, $this->leadService->calculateScore($lead));
    }

    public function test_perfect_score_capped_at_one_hundred(): void
    {
        $lead = new Lead([
            'temperature' => LeadTemperature::Hot,
            'purchase_timeline' => PurchaseTimeline::Immediate,
            'qualification_status' => QualificationStatus::Qualified,
            'budget_min' => 50000000,
            'budget_max' => 120000000,
        ]);

        // 20 + 35 (hot) + 25 (immediate) + 20 (qualified) + 10 (budget) = 110 => capped at 100
        $this->assertEquals(100, $this->leadService->calculateScore($lead));
    }

    public function test_warm_lead_with_mid_term_timeline(): void
    {
        $lead = new Lead([
            'temperature' => LeadTemperature::Warm,
            'purchase_timeline' => PurchaseTimeline::OneToThreeMonths,
            'qualification_status' => QualificationStatus::InReview,
            'budget_max' => 80000000,
        ]);

        // 20 (base) + 20 (warm) + 15 (1-3m) + 10 (in review) + 10 (budget) = 75
        $this->assertEquals(75, $this->leadService->calculateScore($lead));
    }

    public function test_disqualified_lead_cannot_go_below_zero(): void
    {
        $lead = new Lead([
            'temperature' => LeadTemperature::Cold,
            'purchase_timeline' => PurchaseTimeline::Flexible,
            'qualification_status' => QualificationStatus::Disqualified,
            'budget_min' => null,
            'budget_max' => null,
        ]);

        // 20 (base) + 5 (cold) + 0 + -20 (disqualified) + 0 = 5
        $this->assertEquals(5, $this->leadService->calculateScore($lead));
    }
}
