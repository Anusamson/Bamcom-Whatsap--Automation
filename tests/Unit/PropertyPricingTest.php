<?php

namespace Tests\Unit;

use App\Models\Estate;
use App\Models\Promotion;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\Property\PropertyIntelligenceService;
use App\Services\Property\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyPricingTest extends TestCase
{
    use RefreshDatabase;

    protected PropertyService $propertyService;

    protected PropertyIntelligenceService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->propertyService = app(PropertyService::class);
        $this->aiService = app(PropertyIntelligenceService::class);
    }

    public function test_effective_price_returns_regular_price_when_no_promo(): void
    {
        $property = Property::factory()->create();
        $property->prices()->delete();

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 20000000.00,
            'promo_price' => null,
            'initial_deposit' => 4000000.00,
            'is_active' => true,
        ]);

        $property->refresh();

        $this->assertEquals(20000000.00, $property->effective_price);
        $this->assertEquals(20000000.00, $property->regular_price);
        $this->assertNull($property->promo_price);
        $this->assertEquals(4000000.00, $property->initial_deposit);
    }

    public function test_effective_price_returns_promo_price_when_promo_is_lower(): void
    {
        $property = Property::factory()->create();
        $property->prices()->delete();

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 25000000.00,
            'promo_price' => 22000000.00,
            'initial_deposit' => 5000000.00,
            'is_active' => true,
        ]);

        $property->refresh();

        $this->assertEquals(22000000.00, $property->effective_price);
        $this->assertEquals(25000000.00, $property->regular_price);
        $this->assertEquals(22000000.00, $property->promo_price);
    }

    public function test_effective_price_applies_linked_active_percentage_promotion(): void
    {
        $promotion = Promotion::create([
            'name' => 'Flash Sale 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $property = Property::factory()->create([
            'promotion_id' => $promotion->id,
        ]);
        $property->prices()->delete();

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 10000000.00,
            'promo_price' => null,
            'initial_deposit' => 2000000.00,
            'is_active' => true,
        ]);

        $property->refresh();

        // 10% off 10,000,000 = 9,000,000
        $this->assertEquals(9000000.00, $property->effective_price);
    }

    public function test_standard_payment_plans_generation_computes_accurate_installments(): void
    {
        $plans = $this->propertyService->generateStandardPaymentPlans(
            regularPrice: 10000000.00,
            promoPrice: null,
            initialDeposit: 2000000.00
        );

        $this->assertCount(4, $plans);

        // Outright
        $this->assertEquals('Outright Payment', $plans[0]['name']);
        $this->assertEquals(0, $plans[0]['duration_months']);
        $this->assertEquals(10000000.00, $plans[0]['total_amount']);

        // 3 Months (Balance = 8,000,000 / 3 = 2,666,666.67)
        $this->assertEquals('3 Months Installment', $plans[1]['name']);
        $this->assertEquals(3, $plans[1]['duration_months']);
        $this->assertEquals(round(8000000 / 3, 2), $plans[1]['monthly_installment']);

        // 6 Months (5% convenience = 10,500,000 - 2,000,000 = 8,500,000 / 6 = 1,416,666.67)
        $this->assertEquals('6 Months Installment', $plans[2]['name']);
        $this->assertEquals(6, $plans[2]['duration_months']);
        $this->assertEquals(10500000.00, $plans[2]['total_amount']);

        // 12 Months (10% convenience = 11,000,000 - 2,000,000 = 9,000,000 / 12 = 750,000)
        $this->assertEquals('12 Months Installment', $plans[3]['name']);
        $this->assertEquals(12, $plans[3]['duration_months']);
        $this->assertEquals(750000.00, $plans[3]['monthly_installment']);
    }

    public function test_ai_intelligence_service_provides_authoritative_grounding(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Lekki Pearl Estate',
            'location' => 'Ibeju-Lekki',
            'state' => 'Lagos',
            'title_document' => "Governor's Consent",
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Lekki Pearl 500sqm Land',
            'plot_size' => '500sqm',
            'status' => 'published',
            'availability' => 'available',
            'available_units' => 5,
        ]);

        $kb = $this->aiService->getAuthoritativeKnowledgeBase();

        $this->assertEquals('Bamcom Real Estate Live Database', $kb['authoritative_source']);
        $this->assertNotEmpty($kb['ai_guardrails']);
        $this->assertNotEmpty($kb['estates']);
        $this->assertNotEmpty($kb['inventory']);

        // Test markdown system prompt formatting
        $markdown = $this->aiService->formatAiSystemPromptContext();
        $this->assertStringContainsString('Lekki Pearl Estate', $markdown);
        $this->assertStringContainsString('500sqm', $markdown);

        // Test WhatsApp pitch generation
        $pitch = $this->aiService->getWhatsappPitch($property);
        $this->assertStringContainsString('Lekki Pearl 500sqm Land', $pitch);
        $this->assertStringContainsString('Initial Deposit', $pitch);
    }
}
