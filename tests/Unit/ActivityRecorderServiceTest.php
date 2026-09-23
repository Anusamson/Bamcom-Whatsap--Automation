<?php

namespace Tests\Unit;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Property;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityRecorderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityRecorderService $service;

    protected Contact $contact;

    protected Lead $lead;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActivityRecorderService::class);
        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_it_records_generic_crm_activity(): void
    {
        $activity = $this->service->record(
            type: 'status_updated',
            description: 'Lead status changed to qualified',
            contact: $this->contact,
            lead: $this->lead,
            actor: $this->user,
            properties: ['old' => 'new', 'new' => 'qualified'],
        );

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertEquals('status_updated', $activity->activity_type);
        $this->assertEquals($this->contact->id, $activity->contact_id);
        $this->assertEquals($this->lead->id, $activity->lead_id);
        $this->assertEquals($this->user->id, $activity->user_id);
        $this->assertEquals('qualified', $activity->properties['new']);
    }

    public function test_it_records_task_created_activity(): void
    {
        $task = Task::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'title' => 'Follow up on payment schedule',
            'type' => TaskType::FollowUp,
            'priority' => TaskPriority::High,
            'status' => TaskStatus::Pending,
            'created_by_id' => $this->user->id,
        ]);

        $activity = $this->service->recordTaskCreated($task, $this->user);

        $this->assertEquals('task_created', $activity->activity_type);
        $this->assertEquals($this->contact->id, $activity->contact_id);
        $this->assertEquals($this->lead->id, $activity->lead_id);
        $this->assertStringContainsString('Task created', $activity->description);
    }

    public function test_it_records_task_completed_activity(): void
    {
        $task = Task::factory()->completed()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'title' => 'Inspect Silverstone site',
        ]);

        $activity = $this->service->recordTaskCompleted($task, $this->user);

        $this->assertEquals('task_completed', $activity->activity_type);
        $this->assertStringContainsString('Completed task', $activity->description);
    }

    public function test_it_records_note_added_activity(): void
    {
        $note = Note::factory()->pinned()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'user_id' => $this->user->id,
            'content' => 'Client is preparing to issue a bank draft.',
        ]);

        $activity = $this->service->recordNoteAdded($note, $this->user);

        $this->assertEquals('note_added', $activity->activity_type);
        $this->assertEquals($this->contact->id, $activity->contact_id);
        $this->assertTrue($activity->properties['is_pinned']);
    }

    public function test_it_records_inspection_scheduled_activity(): void
    {
        $property = Property::factory()->create();
        $inspection = Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'property_id' => $property->id,
            'representative_id' => $this->user->id,
        ]);

        $activity = $this->service->recordInspectionScheduled($inspection, $this->user);

        $this->assertEquals('inspection_scheduled', $activity->activity_type);
        $this->assertEquals($this->contact->id, $activity->contact_id);
        $this->assertStringContainsString('inspection scheduled', strtolower($activity->description));
    }

    public function test_get_contact_timeline_aggregates_items_with_pinned_priority(): void
    {
        // 1 unpinned note, 1 pinned note
        Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Standard note',
            'is_pinned' => false,
            'created_at' => now()->subHours(2),
        ]);

        $pinnedNote = Note::factory()->pinned()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Important pinned briefing',
            'is_pinned' => true,
            'created_at' => now()->subDay(),
        ]);

        // 1 task
        Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'Timeline task',
            'created_at' => now()->subMinutes(10),
        ]);

        // 1 direct activity
        $this->service->record(
            type: 'status_changed',
            description: 'Status moved to active',
            contact: $this->contact,
            actor: $this->user,
        );

        $timeline = $this->service->getContactTimeline($this->contact);

        $this->assertArrayHasKey('items', $timeline);
        $this->assertArrayHasKey('total', $timeline);
        $this->assertArrayHasKey('pinned_count', $timeline);
        $this->assertEquals(1, $timeline['pinned_count']);

        // First item in timeline should be the pinned note due to pinned prioritization
        $this->assertEquals('note', $timeline['items'][0]['category']);
        $this->assertTrue($timeline['items'][0]['is_pinned']);
        $this->assertEquals('Important pinned briefing', $timeline['items'][0]['description']);
    }

    public function test_get_contact_timeline_filters_by_category(): void
    {
        Note::factory()->create([
            'contact_id' => $this->contact->id,
            'user_id' => $this->user->id,
            'content' => 'Note 1',
        ]);

        Task::factory()->create([
            'contact_id' => $this->contact->id,
            'title' => 'Task 1',
        ]);

        $notesTimeline = $this->service->getContactTimeline($this->contact, ['category' => 'notes']);
        $tasksTimeline = $this->service->getContactTimeline($this->contact, ['category' => 'tasks']);

        foreach ($notesTimeline['items'] as $item) {
            $this->assertEquals('note', $item['category']);
        }

        foreach ($tasksTimeline['items'] as $item) {
            $this->assertEquals('task', $item['category']);
        }
    }
}
