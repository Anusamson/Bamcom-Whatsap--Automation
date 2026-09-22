<?php

namespace Database\Seeders;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TitleDocument;
use App\Models\Estate;
use App\Models\Lead;
use App\Models\Promotion;
use App\Models\Property;
use App\Services\Property\PropertyService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $propertyService = app(PropertyService::class);

        // 1. Promotions
        $earlyBirdPromo = Promotion::firstOrCreate(
            ['slug' => 'independence-early-bird-promo'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Independence Early Bird Promo',
                'code' => 'INDEP15',
                'discount_type' => 'percentage',
                'discount_value' => 15.00,
                'description' => '15% instant reduction on outright purchases and early subscriptions.',
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addDays(50),
                'is_active' => true,
            ]
        );

        $emberPromo = Promotion::firstOrCreate(
            ['slug' => 'ember-bonanza-discount'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Ember Month Bonanza',
                'code' => 'EMBER2M',
                'discount_type' => 'fixed_amount',
                'discount_value' => 2000000.00,
                'description' => '₦2,000,000 flat discount off luxury residential units.',
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addDays(75),
                'is_active' => true,
            ]
        );

        // 2. Estates
        $graceHaven = Estate::firstOrCreate(
            ['slug' => 'grace-haven-estate-ibeju-lekki'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Grace Haven Estate',
                'location' => 'Coastal Road corridor, along Eleko Junction, Ibeju-Lekki',
                'city' => 'Ibeju-Lekki',
                'state' => 'Lagos',
                'landmarks' => '5 mins from Dangote Refinery, Lekki Deep Sea Port, and Pan-Atlantic University',
                'title_document' => TitleDocument::GovernorsConsent->label(),
                'description' => 'A master-planned residential smart community nestled in the fastest appreciating real estate growth corridor of West Africa. Boasts 100% dry land, perimeter fencing, green recreation parks, and 24/7 solar street illumination.',
                'features' => ['100% Dry Table Land', 'Governor\'s Consent', 'Perimeter Fencing & Gatehouse', '24/7 Solar Streetlighting', 'Paved Interlocked Roads', 'Drainage Network', 'Recreational Sports Arena'],
                'cover_image' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'total_land_size' => '50 Hectares',
            ]
        );

        $pacificPalms = Estate::firstOrCreate(
            ['slug' => 'pacific-oceanview-palms-lekki'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Pacific Oceanview Palms',
                'location' => 'Admiralty Way Extension, Lekki Phase 1',
                'city' => 'Lekki',
                'state' => 'Lagos',
                'landmarks' => 'Opposite Lekki Coliseum, 3 minutes to Lekki-Ikoyi Link Bridge',
                'title_document' => TitleDocument::CertificateOfOccupancy->label(),
                'description' => 'Ultra-luxury waterfront development with modern architectural elegance, high-end automated residences, world-class clubhouse, infinity swimming pool, and round-the-clock armed security surveillance.',
                'features' => ['Waterfront Promenade', 'Certificate of Occupancy (C of O)', 'Smart Home Automation', 'Infinity Pool & Gym', 'Centralized Sewage & Clean Water Plant', 'Underground Cabling', 'Fibre-Optic Internet'],
                'cover_image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'total_land_size' => '15 Hectares',
            ]
        );

        $crownHeights = Estate::firstOrCreate(
            ['slug' => 'crown-heights-commercial-epe'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Crown Heights Commercial Hub',
                'location' => 'Expressway Corridor, Alaro City Axis, Epe',
                'city' => 'Epe',
                'state' => 'Lagos',
                'landmarks' => 'Flanked by Alaro City, Epe Resort and Spa, and Proposed Lekki International Airport',
                'title_document' => TitleDocument::Gazette->label(),
                'description' => 'The premium commercial destination for logistics hubs, light industrial warehouses, corporate offices, and mixed-use commercial ventures.',
                'features' => ['Government Gazette', 'Direct Expressway Frontage', 'Heavy Machinery Road Grid', 'Dedicated Transformer Substation', 'Truck Terminal', 'Commercial Zoning Clearance'],
                'cover_image' => 'https://images.unsplash.com/photo-1541888946425-d0fbb186f5f7?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'total_land_size' => '30 Hectares',
            ]
        );

        $maitamaHilltop = Estate::firstOrCreate(
            ['slug' => 'maitama-hilltop-residences-abuja'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Maitama Hilltop Residences',
                'location' => 'Off Gana Street, Maitama District',
                'city' => 'Maitama',
                'state' => 'Abuja (FCT)',
                'landmarks' => 'Overlooking IBB International Golf Course and Transcorp Hilton',
                'title_document' => TitleDocument::CertificateOfOccupancy->label(),
                'description' => 'Exclusive diplomatic enclave reserved for high-net-worth investors and families seeking panoramic views of the federal capital city with unmatched serenity and prestigious neighborhood pedigree.',
                'features' => ['Federal C of O', 'Panoramic City Views', 'Helipad Access', 'Executive Clubhouse', '24/7 Armed Security Patrol', 'High-speed Elevators', 'Biometric Access Control'],
                'cover_image' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'total_land_size' => '8 Hectares',
            ]
        );

        // 3. Properties
        $propertiesData = [
            [
                'estate_id' => $graceHaven->id,
                'promotion_id' => $earlyBirdPromo->id,
                'title' => 'Grace Haven 500sqm Prime Dry Land Plot',
                'property_type' => PropertyType::Land->value,
                'plot_size' => '500sqm',
                'plot_number' => 'GH-BLK-04-P12',
                'location' => 'Grace Haven Estate, Coastal Road, Ibeju-Lekki, Lagos',
                'title_document' => TitleDocument::GovernorsConsent->label(),
                'regular_price' => 18000000.00,
                'promo_price' => 15000000.00,
                'initial_deposit' => 3000000.00,
                'description' => 'Ready-to-build 500sqm standard residential plot situated on elevated dry topography. Zero sand-filling required. Instant plot allocation upon initial deposit completion.',
                'features' => ['Instant Physical Allocation', '100% Dry Land', 'No Omo Onile Encumbrance', 'Deed of Assignment Ready', 'Close to Lekki Free Zone'],
                'availability' => PropertyStatus::Available->value,
                'available_units' => 18,
                'total_units' => 25,
                'status' => 'published',
                'is_featured' => true,
                'cover_image_url' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=1000&q=80',
            ],
            [
                'estate_id' => $graceHaven->id,
                'promotion_id' => null,
                'title' => 'Grace Haven 300sqm Starter Plot',
                'property_type' => PropertyType::Land->value,
                'plot_size' => '300sqm',
                'plot_number' => 'GH-BLK-08-P05',
                'location' => 'Grace Haven Estate, Coastal Road, Ibeju-Lekki, Lagos',
                'title_document' => TitleDocument::GovernorsConsent->label(),
                'regular_price' => 11000000.00,
                'promo_price' => 9800000.00,
                'initial_deposit' => 2000000.00,
                'description' => 'Compact 300sqm plot ideal for building 3-bedroom or 4-bedroom detached terrace home or for high-yield real estate land banking.',
                'features' => ['Fast Capital Appreciation', 'High Rental Demand Area', 'Commercial & Residential Mixed', 'Gated Community Security'],
                'availability' => PropertyStatus::Available->value,
                'available_units' => 7,
                'total_units' => 15,
                'status' => 'published',
                'is_featured' => false,
                'cover_image_url' => 'https://images.unsplash.com/photo-1524813686514-a57563d77d66?auto=format&fit=crop&w=1000&q=80',
            ],
            [
                'estate_id' => $pacificPalms->id,
                'promotion_id' => $emberPromo->id,
                'title' => 'Pacific Palms 4-Bedroom Semi-Detached Duplex + BQ',
                'property_type' => PropertyType::Duplex->value,
                'plot_size' => '450sqm',
                'plot_number' => 'PP-VILLA-07',
                'location' => 'Admiralty Way Extension, Lekki Phase 1, Lagos',
                'title_document' => TitleDocument::CertificateOfOccupancy->label(),
                'regular_price' => 150000000.00,
                'promo_price' => 138000000.00,
                'initial_deposit' => 30000000.00,
                'description' => 'Architectural masterpiece contemporary 4-bedroom semi-detached duplex featuring fully-fitted gourmet kitchen, Italian marble finishings, private home cinema, maid room (BQ), and rooftop terrace.',
                'features' => ['Smart Home Voice Control', 'Private Swimming Pool', 'Fitted Bosch Appliances', 'Rooftop Lounge', 'Automated Access Gate', 'Dedicated 2-Car Port'],
                'availability' => PropertyStatus::Available->value,
                'available_units' => 3,
                'total_units' => 6,
                'status' => 'published',
                'is_featured' => true,
                'cover_image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=80',
            ],
            [
                'estate_id' => $crownHeights->id,
                'promotion_id' => null,
                'title' => 'Crown Heights 1,000sqm Commercial Expressway Frontage',
                'property_type' => PropertyType::Commercial->value,
                'plot_size' => '1,000sqm',
                'plot_number' => 'CH-COMM-01',
                'location' => 'Alaro City Expressway Corridor, Epe, Lagos',
                'title_document' => TitleDocument::Gazette->label(),
                'regular_price' => 45000000.00,
                'promo_price' => 40000000.00,
                'initial_deposit' => 10000000.00,
                'description' => 'Direct expressway facing commercial plot measuring 1,000 square meters. Suitable for mega retail supermarket, filling station, logistics warehouse, or hospital complex.',
                'features' => ['High Traffic Density Frontage', 'Approved Commercial Zoning', 'Heavy Industrial Pavement', 'Direct Dual-Carriageway Access'],
                'availability' => PropertyStatus::Available->value,
                'available_units' => 5,
                'total_units' => 8,
                'status' => 'published',
                'is_featured' => true,
                'cover_image_url' => 'https://images.unsplash.com/photo-1541888946425-d0fbb186f5f7?auto=format&fit=crop&w=1000&q=80',
            ],
            [
                'estate_id' => $maitamaHilltop->id,
                'promotion_id' => null,
                'title' => 'Maitama Hilltop Executive 5-Bedroom Presidential Penthouse',
                'property_type' => PropertyType::Apartment->value,
                'plot_size' => '650sqm',
                'plot_number' => 'MH-TWR-PH-01',
                'location' => 'Off Gana Street, Maitama, Abuja (FCT)',
                'title_document' => TitleDocument::CertificateOfOccupancy->label(),
                'regular_price' => 320000000.00,
                'promo_price' => 295000000.00,
                'initial_deposit' => 60000000.00,
                'description' => 'High-altitude luxury penthouse overlooking the capital cityscape. Features bespoke French windows, private elevator foyer, heated infinity plunge pool, and bulletproof security doors.',
                'features' => ['360 Cityscape Views', 'Private Elevator Foyer', 'Plunge Pool on Sky Terrace', 'Bulletproof Entrance', 'Concierge Butler Service'],
                'availability' => PropertyStatus::Available->value,
                'available_units' => 2,
                'total_units' => 2,
                'status' => 'published',
                'is_featured' => true,
                'cover_image_url' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1000&q=80',
            ],
        ];

        foreach ($propertiesData as $propData) {
            $existing = Property::where('title', $propData['title'])->first();
            if (! $existing) {
                $propertyService->createProperty($propData);
            }
        }

        // 4. Link some existing leads to properties
        $firstProperty = Property::first();
        if ($firstProperty) {
            Lead::whereNull('property_id')->take(3)->update(['property_id' => $firstProperty->id]);
        }
    }
}
