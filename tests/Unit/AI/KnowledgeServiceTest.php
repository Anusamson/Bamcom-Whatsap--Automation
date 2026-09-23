<?php

namespace Tests\Unit\AI;

use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyPrice;
use App\Services\AI\KnowledgeService;
use App\Services\Property\PropertyIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected KnowledgeService $knowledgeService;

    protected function setUp(): void
    {
        parent::setUp();
        $intelligence = new PropertyIntelligenceService;
        $this->knowledgeService = new KnowledgeService($intelligence);
    }

    public function test_can_retrieve_authoritative_knowledge_base(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Oasis Heights Estate',
            'location' => 'Epe, Lagos',
            'title_document' => 'Certificate of Occupancy',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Prime 500 SQM Residential Plot',
            'plot_size' => '500 SQM',
            'available_units' => 12,
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 15000000,
            'promo_price' => 13500000,
            'initial_deposit' => 3000000,
            'is_active' => true,
        ]);

        $kb = $this->knowledgeService->getAuthoritativeKnowledgeBase();

        $this->assertArrayHasKey('estates', $kb);
        $this->assertArrayHasKey('inventory', $kb);
        $this->assertArrayHasKey('company_faq', $kb);
        $this->assertNotEmpty($kb['estates']);
        $this->assertNotEmpty($kb['inventory']);
    }

    public function test_can_search_knowledge_and_build_grounding_prompt(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Grandview Meadows',
            'location' => 'Ibeju-Lekki',
            'title_document' => 'Governor\'s Consent',
        ]);

        $property = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Commercial Corner Piece',
            'available_units' => 5,
        ]);

        PropertyPrice::create([
            'property_id' => $property->id,
            'regular_price' => 25000000,
            'is_active' => true,
        ]);

        $result = $this->knowledgeService->searchKnowledge('Ibeju-Lekki');

        $this->assertNotEmpty($result['matched_estates']);
        $this->assertNotEmpty($result['matched_properties']);

        $promptContext = $this->knowledgeService->buildPromptContext('Ibeju-Lekki');
        $this->assertStringContainsString('Grandview Meadows', $promptContext);
        $this->assertStringContainsString('Governor\'s Consent', $promptContext);
        $this->assertStringContainsString('AUTHORITATIVE BAMCOM REAL ESTATE GROUND TRUTH', $promptContext);
    }
}
