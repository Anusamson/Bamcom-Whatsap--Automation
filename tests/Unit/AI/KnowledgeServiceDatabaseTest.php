<?php

namespace Tests\Unit\AI;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\Estate;
use App\Models\KnowledgeRecord;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\KnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeServiceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected KnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->knowledgeService = app(KnowledgeService::class);
    }

    public function test_only_active_knowledge_records_are_available_to_ai(): void
    {
        // 1. Active record
        KnowledgeRecord::factory()->create([
            'title' => 'Active Company Policy',
            'category' => KnowledgeCategory::CompanyInformation,
            'content' => 'Official Bamcom registration details.',
            'status' => KnowledgeStatus::Active,
        ]);

        // 2. Draft record (MUST NOT appear in AI search)
        KnowledgeRecord::factory()->create([
            'title' => 'Secret Draft Strategy',
            'category' => KnowledgeCategory::SalesInformation,
            'content' => 'Internal confidential discounts.',
            'status' => KnowledgeStatus::Draft,
        ]);

        // 3. Expired record (MUST NOT appear in AI search)
        KnowledgeRecord::factory()->create([
            'title' => 'Expired Summer Promo',
            'category' => KnowledgeCategory::SalesInformation,
            'content' => 'Old 2024 promo pricing.',
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->subMonths(3),
            'expiration_date' => now()->subDay(),
        ]);

        // 4. Future effective record (MUST NOT appear in AI search)
        KnowledgeRecord::factory()->create([
            'title' => 'Future New Year Promo',
            'category' => KnowledgeCategory::SalesInformation,
            'content' => '2027 promo pricing.',
            'status' => KnowledgeStatus::Active,
            'effective_date' => now()->addMonth(),
        ]);

        $results = $this->knowledgeService->searchActiveKnowledge('');
        $titles = $results->pluck('title')->toArray();

        $this->assertContains('Active Company Policy', $titles);
        $this->assertNotContains('Secret Draft Strategy', $titles);
        $this->assertNotContains('Expired Summer Promo', $titles);
        $this->assertNotContains('Future New Year Promo', $titles);
    }

    public function test_property_prices_and_availability_must_strictly_come_from_property_database(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Atlantic Bay Haven',
            'location' => 'Ibeju-Lekki',
            'title_document' => "Governor's Consent",
            'status' => 'active',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Atlantic Premium 500sqm Plot',
            'plot_size' => '500sqm',
            'available_units' => 7,
            'availability' => 'available',
            'status' => 'published',
            'property_type' => 'residential',
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 25_000_000,
            'promo_price' => 22_500_000,
            'initial_deposit' => 5_000_000,
            'is_active' => true,
        ]);

        // Search through knowledge service
        $knowledge = $this->knowledgeService->searchKnowledge('Atlantic');

        $this->assertNotEmpty($knowledge['matched_properties']);
        $matchedProp = $knowledge['matched_properties'][0];

        // Assert price and units directly reflect the property database
        $this->assertEquals(25_000_000, $matchedProp['pricing']['regular_price']);
        $this->assertEquals(22_500_000, $matchedProp['pricing']['effective_price']);
        $this->assertEquals(7, $matchedProp['available_units']);
        $this->assertEquals('Atlantic Bay Haven', $matchedProp['estate_name']);

        // Update property database prices and availability
        $property->activePrice->update([
            'regular_price' => 30_000_000,
            'promo_price' => null,
            'initial_deposit' => 6_000_000,
        ]);
        $property->update(['available_units' => 4]);

        // Re-query knowledge service
        $refreshedKnowledge = $this->knowledgeService->searchKnowledge('Atlantic');
        $refreshedProp = $refreshedKnowledge['matched_properties'][0];

        // Verify live changes in the database are immediately reflected
        $this->assertEquals(30_000_000, $refreshedProp['pricing']['regular_price']);
        $this->assertEquals(30_000_000, $refreshedProp['pricing']['effective_price']);
        $this->assertEquals(4, $refreshedProp['available_units']);
    }

    public function test_prompt_context_integrates_categorized_active_knowledge_and_live_inventory(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Grace Haven Estate',
            'location' => 'Epe Expressway',
            'title_document' => 'C of O',
            'status' => 'active',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Grace Villa Plot',
            'plot_size' => '600sqm',
            'available_units' => 12,
            'availability' => 'available',
            'status' => 'published',
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 15_000_000,
            'is_active' => true,
        ]);

        KnowledgeRecord::factory()->create([
            'title' => 'CAC Registration Number',
            'category' => KnowledgeCategory::CompanyInformation,
            'content' => 'Bamcom is incorporated with RC 1849204.',
            'status' => KnowledgeStatus::Active,
        ]);

        KnowledgeRecord::factory()->create([
            'title' => 'Overcoming Omo-Onile Land Grabbing Doubts',
            'category' => KnowledgeCategory::ObjectionHandling,
            'content' => 'All land is 100% excised and surveyed.',
            'status' => KnowledgeStatus::Active,
        ]);

        KnowledgeRecord::factory()->create([
            'title' => 'Secret Inactive Script',
            'category' => KnowledgeCategory::SalesScript,
            'content' => 'Never quote this.',
            'status' => KnowledgeStatus::Draft,
        ]);

        $context = $this->knowledgeService->buildPromptContext('Grace Haven');

        // Verify live database inventory is present
        $this->assertStringContainsString('Grace Haven Estate', $context);
        $this->assertStringContainsString('Grace Villa Plot', $context);
        $this->assertStringContainsString('Units Available: 12', $context);

        // Verify active knowledge records are present
        $this->assertStringContainsString('CAC Registration Number', $context);
        $this->assertStringContainsString('Overcoming Omo-Onile Land Grabbing Doubts', $context);

        // Verify draft records are strictly absent
        $this->assertStringNotContainsString('Secret Inactive Script', $context);
    }
}
