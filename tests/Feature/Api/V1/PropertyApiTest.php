<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PropertyType;
use App\Enums\TitleDocument;
use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_properties_via_api(): void
    {
        Property::factory()->count(5)->create();

        $response = $this->getJson(route('api.v1.properties.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'data',
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->assertEquals(5, count($response->json('data')));
    }

    public function test_can_get_single_property_details_via_api(): void
    {
        $property = Property::factory()->create([
            'title' => 'Pacific Crest Villa Plot',
            'plot_size' => '500sqm',
        ]);

        $response = $this->getJson(route('api.v1.properties.show', $property));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $property->id,
                    'title' => 'Pacific Crest Villa Plot',
                ],
            ])
            ->assertJsonStructure([
                'status',
                'data',
                'ai_facts' => [
                    'id',
                    'title',
                    'pricing' => ['regular_price', 'effective_price', 'initial_deposit'],
                    'payment_plans',
                ],
            ]);
    }

    public function test_can_get_authoritative_ai_knowledge_context(): void
    {
        $estate = Estate::factory()->create([
            'name' => 'Atlantic Bay Palms',
            'location' => 'Ibeju-Lekki',
            'state' => 'Lagos',
        ]);

        Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Atlantic Bay 500sqm Land',
            'status' => 'published',
            'availability' => 'available',
            'available_units' => 10,
        ]);

        // JSON format
        $responseJson = $this->getJson(route('api.v1.properties.ai-context'));

        $responseJson->assertOk()
            ->assertJson([
                'status' => 'success',
                'format' => 'json',
                'data' => [
                    'authoritative_source' => 'Bamcom Real Estate Live Database',
                    'currency' => 'NGN (₦)',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'authoritative_source',
                    'ai_guardrails',
                    'estates',
                    'inventory',
                ],
            ]);

        // Markdown format
        $responseMd = $this->getJson(route('api.v1.properties.ai-context', ['format' => 'markdown']));

        $responseMd->assertOk()
            ->assertJson([
                'status' => 'success',
                'format' => 'markdown',
            ]);

        $this->assertStringContainsString('AUTHORITATIVE REAL ESTATE INVENTORY', $responseMd->json('context'));
        $this->assertStringContainsString('Atlantic Bay Palms', $responseMd->json('context'));
    }

    public function test_can_query_properties_for_ai_function_calling(): void
    {
        $estate = Estate::factory()->create(['name' => 'Crown Park']);

        $prop1 = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Affordable 300sqm Plot',
            'property_type' => PropertyType::Land->value,
            'location' => 'Ibeju-Lekki, Lagos',
            'title_document' => TitleDocument::GovernorsConsent->label(),
            'status' => 'published',
            'availability' => 'available',
            'available_units' => 5,
        ]);
        $prop1->prices()->delete();
        PropertyPrice::create([
            'property_id' => $prop1->id,
            'regular_price' => 12000000.00,
            'promo_price' => 10000000.00,
            'initial_deposit' => 2000000.00,
            'is_active' => true,
        ]);

        $prop2 = Property::factory()->create([
            'estate_id' => $estate->id,
            'title' => 'Luxury Mansion Duplex',
            'property_type' => PropertyType::Duplex->value,
            'location' => 'Ikoyi, Lagos',
            'title_document' => TitleDocument::CertificateOfOccupancy->label(),
            'status' => 'published',
            'availability' => 'available',
            'available_units' => 1,
        ]);
        $prop2->prices()->delete();
        PropertyPrice::create([
            'property_id' => $prop2->id,
            'regular_price' => 150000000.00,
            'is_active' => true,
        ]);

        // Query for Land under 15 million in Ibeju-Lekki
        $response = $this->postJson(route('api.v1.properties.ai-query'), [
            'location' => 'Ibeju-Lekki',
            'max_price' => 15000000,
            'property_type' => PropertyType::Land->value,
            'in_stock_only' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'count' => 1,
                'authoritative_source' => 'Bamcom Live Database',
            ]);

        $results = $response->json('data');
        $this->assertEquals('Affordable 300sqm Plot', $results[0]['title']);
        $this->assertEquals(10000000.00, $results[0]['pricing']['effective_price']);
    }
}
