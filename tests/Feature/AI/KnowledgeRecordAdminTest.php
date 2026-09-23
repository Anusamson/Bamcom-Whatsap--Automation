<?php

namespace Tests\Feature\AI;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Enums\UserRole;
use App\Models\KnowledgeRecord;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeRecordAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole(UserRole::SuperAdmin->value);

        $this->regularUser = User::factory()->create();
    }

    public function test_guests_cannot_access_knowledge_base(): void
    {
        $response = $this->get(route('knowledge.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_access_knowledge_base(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('knowledge.index'));
        $response->assertForbidden();
    }

    public function test_super_admin_can_view_knowledge_index(): void
    {
        KnowledgeRecord::factory()->count(5)->create();

        $response = $this->actingAs($this->superAdmin)->get(route('knowledge.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Knowledge/Index')
            ->has('records.data', 5)
            ->has('categories', 8)
            ->has('statuses', 3)
            ->has('stats')
        );
    }

    public function test_admin_can_filter_knowledge_by_category(): void
    {
        KnowledgeRecord::factory()->create([
            'category' => KnowledgeCategory::Faq,
            'title' => 'FAQ Question One',
        ]);

        KnowledgeRecord::factory()->create([
            'category' => KnowledgeCategory::ObjectionHandling,
            'title' => 'Objection Response One',
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('knowledge.index', [
            'category' => 'faq',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Knowledge/Index')
            ->has('records.data', 1)
            ->where('records.data.0.title', 'FAQ Question One')
        );
    }

    public function test_admin_can_filter_knowledge_by_status(): void
    {
        KnowledgeRecord::factory()->create([
            'title' => 'Active Knowledge',
            'status' => KnowledgeStatus::Active,
        ]);

        KnowledgeRecord::factory()->create([
            'title' => 'Draft Knowledge',
            'status' => KnowledgeStatus::Draft,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('knowledge.index', [
            'status' => 'draft',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Knowledge/Index')
            ->has('records.data', 1)
            ->where('records.data.0.title', 'Draft Knowledge')
        );
    }

    public function test_admin_can_create_knowledge_record_for_each_category(): void
    {
        foreach (KnowledgeCategory::cases() as $category) {
            $data = [
                'title' => "Knowledge for {$category->label()}",
                'category' => $category->value,
                'content' => "Authoritative test content governing {$category->label()}.",
                'status' => 'active',
                'priority' => 75,
                'effective_date' => now()->toDateString(),
                'expiration_date' => now()->addYear()->toDateString(),
                'keywords' => 'test, knowledge, real-estate',
            ];

            $response = $this->actingAs($this->superAdmin)->post(route('knowledge.store'), $data);

            $response->assertRedirect(route('knowledge.index', ['category' => $category->value]));
            $this->assertDatabaseHas('knowledge_records', [
                'title' => "Knowledge for {$category->label()}",
                'category' => $category->value,
                'priority' => 75,
                'created_by' => $this->superAdmin->id,
            ]);
        }
    }

    public function test_store_validation_prevents_invalid_data(): void
    {
        // 1. Missing required fields
        $response = $this->actingAs($this->superAdmin)->post(route('knowledge.store'), []);
        $response->assertSessionHasErrors(['title', 'category', 'content', 'status']);

        // 2. Invalid category
        $response = $this->actingAs($this->superAdmin)->post(route('knowledge.store'), [
            'title' => 'Test Title',
            'category' => 'invalid_category_xyz',
            'content' => 'Some content',
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors('category');

        // 3. Expiration date before effective date
        $response = $this->actingAs($this->superAdmin)->post(route('knowledge.store'), [
            'title' => 'Date Test',
            'category' => 'faq',
            'content' => 'Content here',
            'status' => 'active',
            'effective_date' => '2026-10-01',
            'expiration_date' => '2026-09-01',
        ]);
        $response->assertSessionHasErrors('expiration_date');
    }

    public function test_admin_can_view_knowledge_record_detail(): void
    {
        $record = KnowledgeRecord::factory()->create([
            'title' => 'Legal Title Verification Rules',
            'category' => KnowledgeCategory::Faq,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('knowledge.show', $record->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Knowledge/Show')
            ->where('record.id', $record->id)
            ->where('record.title', 'Legal Title Verification Rules')
            ->has('record.is_active_for_ai')
        );
    }

    public function test_admin_can_update_knowledge_record(): void
    {
        $record = KnowledgeRecord::factory()->create([
            'title' => 'Initial Title',
            'category' => KnowledgeCategory::InspectionPolicy,
            'priority' => 10,
        ]);

        $response = $this->actingAs($this->superAdmin)->put(route('knowledge.update', $record->id), [
            'title' => 'Updated Title for Inspections',
            'category' => KnowledgeCategory::InspectionPolicy->value,
            'content' => 'New inspection transport policies.',
            'status' => 'active',
            'priority' => 90,
        ]);

        $response->assertRedirect(route('knowledge.index', ['category' => 'inspection_policy']));
        $this->assertDatabaseHas('knowledge_records', [
            'id' => $record->id,
            'title' => 'Updated Title for Inspections',
            'priority' => 90,
            'updated_by' => $this->superAdmin->id,
        ]);
    }

    public function test_admin_can_toggle_knowledge_record_status(): void
    {
        $record = KnowledgeRecord::factory()->create([
            'status' => KnowledgeStatus::Active,
        ]);

        $response = $this->actingAs($this->superAdmin)->patch(route('knowledge.toggle-status', $record->id));
        $response->assertStatus(302);

        $this->assertEquals(KnowledgeStatus::Draft, $record->fresh()->status);

        $response = $this->actingAs($this->superAdmin)->patch(route('knowledge.toggle-status', $record->id));
        $response->assertStatus(302);

        $this->assertEquals(KnowledgeStatus::Active, $record->fresh()->status);
    }

    public function test_admin_can_delete_knowledge_record(): void
    {
        $record = KnowledgeRecord::factory()->create([
            'category' => KnowledgeCategory::SalesScript,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('knowledge.destroy', $record->id));

        $response->assertRedirect(route('knowledge.index', ['category' => 'sales_script']));
        $this->assertSoftDeleted('knowledge_records', [
            'id' => $record->id,
        ]);
    }
}
