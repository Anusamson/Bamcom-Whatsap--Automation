<?php

namespace Database\Factories;

use App\Models\AiSalesInsight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiSalesInsight>
 */
class AiSalesInsightFactory extends Factory
{
    protected $model = AiSalesInsight::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'title' => 'Executive Sales Intelligence & Objection Briefing',
            'period' => '30d',
            'date_from' => now()->subDays(30)->toDateString(),
            'date_to' => now()->toDateString(),
            'executive_summary' => 'Comprehensive CRM analysis indicates strong buyer interest in Abuja estates, particularly Peace Court and Grace Heights. Price point elasticity is concentrated within ₦15M-₦35M with flexible 12-month payment structures accelerating deal closures.',
            'metrics_snapshot' => [
                'frequently_requested_estates' => [
                    ['estate' => 'Peace Court Estate', 'count' => 18, 'percentage' => 45.0],
                    ['estate' => 'Grace Heights Estate', 'count' => 12, 'percentage' => 30.0],
                ],
                'common_customer_questions' => [
                    ['category' => 'Land Title & Documentation', 'count' => 24, 'percentage' => 40.0],
                    ['category' => 'Payment Plan Options', 'count' => 18, 'percentage' => 30.0],
                ],
                'common_objections' => [
                    ['objection' => 'Budget / Price Resistance', 'count' => 15, 'percentage' => 37.5],
                    ['objection' => 'Payment Flexibility', 'count' => 10, 'percentage' => 25.0],
                ],
                'requested_price_ranges' => [
                    ['range' => '₦10M - ₦25M', 'count' => 14, 'percentage' => 35.0],
                    ['range' => '₦25M - ₦50M', 'count' => 12, 'percentage' => 30.0],
                ],
                'requested_payment_plans' => [
                    ['plan' => '12 Months Installment', 'count' => 16, 'percentage' => 40.0],
                    ['plan' => 'Outright Payment', 'count' => 12, 'percentage' => 30.0],
                ],
                'handover_reasons' => [
                    ['reason' => 'Price Negotiation', 'count' => 8, 'percentage' => 40.0],
                    ['reason' => 'Custom Contract Terms', 'count' => 6, 'percentage' => 30.0],
                ],
                'lost_deal_reasons' => [
                    ['reason' => 'Price out of budget', 'count' => 7, 'lost_value' => 35000000],
                    ['reason' => 'Preferred different location', 'count' => 4, 'lost_value' => 20000000],
                ],
                'lead_to_inspection_conversion' => [
                    'total_leads' => 40,
                    'leads_with_inspections' => 14,
                    'conversion_rate' => 35.0,
                ],
                'inspection_to_sale_conversion' => [
                    'completed_inspections' => 12,
                    'won_deals' => 5,
                    'conversion_rate' => 41.7,
                    'won_revenue' => 85000000.0,
                ],
            ],
            'insights' => [
                'frequently_requested_estates' => 'Peace Court Estate is currently the top requested development, generating 45% of all estate-specific inquiries.',
                'common_customer_questions' => '40% of inbound customer questions center on land title authenticity (C of O and Governor Consent).',
                'common_objections' => 'Initial price resistance is the primary objection, cited in 37.5% of stalled deals.',
                'requested_price_ranges' => 'Demand peaks sharply between ₦10M and ₦50M, accounting for 65% of qualified leads.',
                'requested_payment_plans' => '12-month installment schedules are preferred over outright purchases by a 4:3 margin.',
                'handover_reasons' => 'Price discount negotiations trigger 40% of all AI-to-human representative escalations.',
                'lost_deal_reasons' => 'Unmet budget expectations accounted for ₦35M in lost pipeline opportunities.',
                'lead_to_inspection_conversion' => '35.0% of leads progressed to physical site visits (14 out of 40 leads).',
                'inspection_to_sale_conversion' => '41.7% of completed inspections successfully closed into won deals, generating ₦85M.',
            ],
            'recommendations' => [
                'Offer pre-approved 12-month payment schedules on Peace Court plots to overcome budget resistance.',
                'Equip AI agents with verified title documentation proofs upfront to address title inquiries before objection stage.',
                'Prioritize post-inspection follow-ups within 24 hours, where conversion rate is highest at 41.7%.',
            ],
            'generated_by_user_id' => User::factory(),
            'model_used' => 'gemini-1.5-pro',
            'tokens_used' => 650,
            'duration_ms' => 420.5,
        ];
    }
}
