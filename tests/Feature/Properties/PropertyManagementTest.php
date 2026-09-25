<?php

namespace Tests\Feature\Properties;

use App\Enums\PermissionEnum;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TitleDocument;
use App\Enums\UserRole;
use App\Models\Estate;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\PropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PropertyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => UserRole::Admin->value, 'guard_name' => 'web']);
        $adminRole->givePermissionTo([
            PermissionEnum::PropertiesView->value,
            PermissionEnum::PropertiesCreate->value,
            PermissionEnum::PropertiesEdit->value,
            PermissionEnum::PropertiesDelete->value,
        ]);

        $this->adminUser = User::factory()->create(['role' => UserRole::Admin]);
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->create(['role' => UserRole::SalesExecutive]);
    }

    public function test_unauthorized_user_cannot_view_property_catalog(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)->get(route('properties.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_property_catalog_with_filters(): void
    {
        $estate = Estate::factory()->create(['name' => 'Grace Haven Estate']);
        Property::factory()->count(10)->create(['estate_id' => $estate->id]);

        $response = $this->actingAs($this->adminUser)->get(route('properties.index', [
            'estate_id' => $estate->id,
        ]));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Properties/Index')
                ->has('properties.data', 10)
                ->has('metrics')
                ->has('estates')
            );
    }

    public function test_admin_can_create_property_with_required_fields_and_pricing(): void
    {
        $estate = Estate::factory()->create();

        $payload = [
            'estate_id' => $estate->id,
            'title' => 'Oceanview 500sqm Dry Land',
            'property_type' => PropertyType::Land->value,
            'plot_size' => '500sqm',
            'plot_number' => 'OV-BLK-01',
            'location' => 'Lekki Coastal Road, Lagos',
            'title_document' => TitleDocument::GovernorsConsent->label(),
            'regular_price' => 25000000.00,
            'promo_price' => 22000000.00,
            'initial_deposit' => 5000000.00,
            'description' => '100% elevated table dry land with instant deed allocation.',
            'features' => ['Dry Land', 'Solar Lights', 'Gatehouse'],
            'availability' => PropertyStatus::Available->value,
            'available_units' => 12,
            'total_units' => 15,
            'status' => 'published',
            'is_featured' => true,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('properties.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('properties', [
            'title' => 'Oceanview 500sqm Dry Land',
            'property_type' => 'land',
            'plot_size' => '500sqm',
            'available_units' => 12,
            'status' => 'published',
        ]);

        $property = Property::where('title', 'Oceanview 500sqm Dry Land')->first();
        $this->assertNotNull($property);
        $this->assertNotNull($property->activePrice);
        $this->assertEquals(25000000.00, (float) $property->activePrice->regular_price);
        $this->assertEquals(22000000.00, (float) $property->activePrice->promo_price);
        $this->assertEquals(22000000.00, (float) $property->effective_price);
    }

    public function test_admin_can_view_property_360_profile(): void
    {
        $property = Property::factory()->create();

        $response = $this->actingAs($this->adminUser)->get(route('properties.show', $property));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Properties/Show')
                ->has('property')
                ->has('aiFactSheet')
                ->has('whatsappPitch')
            );
    }

    public function test_admin_can_update_property(): void
    {
        $property = Property::factory()->create(['title' => 'Initial Title']);

        $response = $this->actingAs($this->adminUser)->put(route('properties.update', $property), [
            'title' => 'Updated Luxury Title',
            'plot_size' => '600sqm',
            'property_type' => PropertyType::Residential->value,
            'regular_price' => 35000000.00,
        ]);

        $response->assertRedirect(route('properties.show', $property));

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'title' => 'Updated Luxury Title',
            'plot_size' => '600sqm',
        ]);
    }

    public function test_admin_can_delete_property(): void
    {
        $property = Property::factory()->create();

        $response = $this->actingAs($this->adminUser)->delete(route('properties.destroy', $property));

        $response->assertRedirect(route('properties.index'));

        $this->assertSoftDeleted('properties', ['id' => $property->id]);
    }

    public function test_estate_crud_management(): void
    {
        // 1. Create Estate
        $estatePayload = [
            'name' => 'Royal Palm Estate',
            'location' => 'Along Lekki-Epe Expressway, Epe',
            'city' => 'Epe',
            'state' => 'Lagos',
            'landmarks' => 'Opposite Alaro City',
            'title_document' => TitleDocument::CertificateOfOccupancy->label(),
            'description' => 'Prime master planned estate community.',
            'features' => ['Electricity', 'Paved Roads'],
            'status' => 'active',
            'total_land_size' => '40 Hectares',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('estates.store'), $estatePayload);
        $response->assertRedirect();

        $estate = Estate::where('name', 'Royal Palm Estate')->first();
        $this->assertNotNull($estate);

        // 2. View Estate Show
        $showResponse = $this->actingAs($this->adminUser)->get(route('estates.show', $estate));
        $showResponse->assertOk()
            ->assertInertia(fn ($page) => $page->component('Estates/Show')->has('estate'));

        // 3. Update Estate
        $updateResponse = $this->actingAs($this->adminUser)->put(route('estates.update', $estate), [
            'name' => 'Royal Palm Estate Phase 2',
        ]);
        $updateResponse->assertRedirect(route('estates.show', $estate));
        $this->assertEquals('Royal Palm Estate Phase 2', $estate->fresh()->name);

        // 4. Delete Estate
        $deleteResponse = $this->actingAs($this->adminUser)->delete(route('estates.destroy', $estate));
        $deleteResponse->assertRedirect(route('estates.index'));
        $this->assertSoftDeleted('estates', ['id' => $estate->id]);
    }

    public function test_recording_unit_sale_decrements_available_units_and_marks_sold_out(): void
    {
        $propertyService = app(PropertyService::class);

        $property = Property::factory()->create([
            'available_units' => 2,
            'availability' => PropertyStatus::Available->value,
        ]);

        // Sell 1 unit
        $propertyService->recordUnitSale($property, 1);
        $this->assertEquals(1, $property->fresh()->available_units);
        $this->assertEquals(PropertyStatus::Available->value, $property->fresh()->availability);

        // Sell remaining unit -> becomes sold_out
        $propertyService->recordUnitSale($property, 1);
        $this->assertEquals(0, $property->fresh()->available_units);
        $this->assertEquals(PropertyStatus::SoldOut->value, $property->fresh()->availability);
    }

    public function test_admin_can_create_property_with_uploaded_jpeg_cover_photo(): void
    {
        Storage::fake('public');

        $estate = Estate::factory()->create();
        $coverFile = UploadedFile::fake()->image('property_hero.jpeg', 1200, 800);

        $payload = [
            'estate_id' => $estate->id,
            'title' => 'Oceanview Villa JPEG Plot',
            'property_type' => PropertyType::Land->value,
            'plot_size' => '600sqm',
            'regular_price' => 30000000.00,
            'cover_image' => $coverFile,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('properties.store'), $payload);

        $response->assertRedirect();
        $property = Property::where('title', 'Oceanview Villa JPEG Plot')->first();
        $this->assertNotNull($property);
        $this->assertNotNull($property->primaryMedia);
        $this->assertTrue($property->primaryMedia->is_primary);
        $this->assertSame('image', $property->primaryMedia->media_type);

        Storage::disk('public')->assertExists($property->primaryMedia->file_path);
    }

    public function test_admin_can_create_property_with_uploaded_png_cover_photo(): void
    {
        Storage::fake('public');

        $estate = Estate::factory()->create();
        $coverFile = UploadedFile::fake()->image('property_hero.png', 1200, 800);

        $payload = [
            'estate_id' => $estate->id,
            'title' => 'Oceanview Villa PNG Plot',
            'property_type' => PropertyType::Land->value,
            'plot_size' => '500sqm',
            'regular_price' => 20000000.00,
            'cover_image' => $coverFile,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('properties.store'), $payload);

        $response->assertRedirect();
        $property = Property::where('title', 'Oceanview Villa PNG Plot')->first();
        $this->assertNotNull($property);
        $this->assertNotNull($property->primaryMedia);

        Storage::disk('public')->assertExists($property->primaryMedia->file_path);
    }

    public function test_property_creation_rejects_invalid_picture_format(): void
    {
        Storage::fake('public');

        $estate = Estate::factory()->create();
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');

        $payload = [
            'estate_id' => $estate->id,
            'title' => 'Invalid Picture Property',
            'property_type' => PropertyType::Land->value,
            'plot_size' => '500sqm',
            'regular_price' => 15000000.00,
            'cover_image' => $invalidFile,
        ];

        $response = $this->actingAs($this->adminUser)->post(route('properties.store'), $payload);

        $response->assertSessionHasErrors(['cover_image']);
        $this->assertDatabaseMissing('properties', ['title' => 'Invalid Picture Property']);
    }

    public function test_admin_can_update_property_with_new_uploaded_picture(): void
    {
        Storage::fake('public');

        $property = Property::factory()->create();
        $initialFile = UploadedFile::fake()->image('initial.jpg', 600, 400);
        $initialPath = $initialFile->store('properties/covers', 'public');
        $property->media()->create([
            'media_type' => 'image',
            'file_path' => $initialPath,
            'file_url' => asset('storage/'.$initialPath),
            'is_primary' => true,
        ]);

        Storage::disk('public')->assertExists($initialPath);

        $newFile = UploadedFile::fake()->image('replacement.png', 1200, 800);

        $response = $this->actingAs($this->adminUser)->put(route('properties.update', $property), [
            'title' => 'Updated Picture Property',
            'plot_size' => '500sqm',
            'property_type' => PropertyType::Land->value,
            'regular_price' => 22000000.00,
            'cover_image' => $newFile,
        ]);

        $response->assertRedirect(route('properties.show', $property));

        $updatedMedia = $property->fresh()->primaryMedia;
        $this->assertNotNull($updatedMedia);
        $this->assertNotEquals($initialPath, $updatedMedia->file_path);
        Storage::disk('public')->assertExists($updatedMedia->file_path);
        Storage::disk('public')->assertMissing($initialPath);
    }

    public function test_admin_can_remove_existing_cover_photo(): void
    {
        Storage::fake('public');

        $property = Property::factory()->create();
        $initialFile = UploadedFile::fake()->image('initial.jpg', 600, 400);
        $initialPath = $initialFile->store('properties/covers', 'public');
        $property->media()->create([
            'media_type' => 'image',
            'file_path' => $initialPath,
            'file_url' => asset('storage/'.$initialPath),
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('properties.update', $property), [
            'title' => $property->title,
            'plot_size' => $property->plot_size,
            'property_type' => PropertyType::Land->value,
            'regular_price' => 10000000.00,
            'remove_cover_image' => true,
        ]);

        $response->assertRedirect(route('properties.show', $property));
        $this->assertNull($property->fresh()->primaryMedia);
        Storage::disk('public')->assertMissing($initialPath);
    }
}
