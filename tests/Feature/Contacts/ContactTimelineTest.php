<?php

namespace Tests\Feature\Contacts;

use App\Enums\PermissionEnum;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (PermissionEnum::cases() as $perm) {
            Permission::firstOrCreate(['name' => $perm->value, 'guard_name' => 'web']);
        }

        $superAdminRole = Role::firstOrCreate(['name' => UserRole::SuperAdmin->value, 'guard_name' => 'web']);
        $this->user = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->user->assignRole($superAdminRole);

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_contact_show_page_renders_with_timeline_and_task_props(): void
    {
        Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Sample note for timeline display',
        ]);

        Task::factory()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->user->id,
            'title' => 'Sample task for timeline',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('contacts.show', $this->contact));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Show')
                ->has('contact')
                ->has('timeline.items')
                ->has('timeline.total')
                ->has('users')
                ->has('taskPriorities')
                ->has('taskTypes')
            );
    }

    public function test_contact_timeline_endpoint_returns_json_items(): void
    {
        Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'First note',
        ]);

        Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'First task',
            'type' => TaskType::FollowUp,
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Pending,
        ]);

        app(ActivityRecorderService::class)->record(
            type: 'contact_verified',
            description: 'Contact phone verified via WhatsApp',
            contact: $this->contact,
            actor: $this->user,
        );

        $response = $this->actingAs($this->user)
            ->getJson(route('contacts.timeline', $this->contact));

        $response->assertOk()
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'category',
                        'activity_type',
                        'title',
                        'description',
                        'created_at',
                        'is_pinned',
                        'icon',
                    ],
                ],
                'total',
                'pinned_count',
            ]);

        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_contact_timeline_endpoint_supports_filtering_by_category(): void
    {
        Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Only Note',
        ]);

        Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'Only Task',
        ]);

        $responseNotes = $this->actingAs($this->user)
            ->getJson(route('contacts.timeline', [$this->contact, 'category' => 'notes']));

        $responseNotes->assertOk();
        foreach ($responseNotes->json('items') as $item) {
            $this->assertEquals('note', $item['category']);
        }

        $responseTasks = $this->actingAs($this->user)
            ->getJson(route('contacts.timeline', [$this->contact, 'category' => 'tasks']));

        $responseTasks->assertOk();
        foreach ($responseTasks->json('items') as $item) {
            $this->assertEquals('task', $item['category']);
        }
    }
}
