<?php

namespace Tests\Unit;

use App\Enums\InspectionStatus;
use App\Enums\LeadTemperature;
use App\Events\InspectionCancelled;
use App\Events\InspectionCompleted;
use App\Events\InspectionConfirmed;
use App\Events\InspectionNoShow;
use App\Events\InspectionRescheduled;
use App\Events\InspectionScheduled;
use App\Events\InspectionStatusChanged;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Services\Inspection\InspectionService;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InspectionService $service;

    protected Contact $contact;

    protected Lead $lead;

    protected User $representative;

    protected User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeadScoringRuleSeeder::class);
        $this->service = app(InspectionService::class);

        $this->creator = User::factory()->create();
        $this->representative = User::factory()->create();

        $this->contact = Contact::factory()->create();
        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 20,
            'temperature' => LeadTemperature::Cold,
        ]);
    }

    public function test_can_schedule_inspection_with_all_attributes(): void
    {
        Event::fake([InspectionScheduled::class, InspectionStatusChanged::class]);

        $property = Property::factory()->create(['title' => 'Lekki Palm Grove']);

        $inspection = $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'property_id' => $property->id,
            'estate_name' => 'Lekki Palm Grove Estate',
            'representative_id' => $this->representative->id,
            'inspection_date' => now()->addDays(2)->format('Y-m-d'),
            'inspection_time' => '10:00 AM',
            'meeting_point' => 'Plot 12 Admiralty Way, Lekki Phase 1',
            'customer_notes' => 'Client coming with spouse and private architect.',
            'sales_notes' => 'High net worth investor.',
        ], $this->creator);

        $this->assertInstanceOf(Inspection::class, $inspection);
        $this->assertEquals($this->contact->id, $inspection->contact_id);
        $this->assertEquals($this->lead->id, $inspection->lead_id);
        $this->assertEquals($property->id, $inspection->property_id);
        $this->assertEquals($this->representative->id, $inspection->representative_id);
        $this->assertEquals(InspectionStatus::Scheduled, $inspection->status);
        $this->assertEquals('10:00 AM', $inspection->inspection_time);
        $this->assertEquals('Plot 12 Admiralty Way, Lekki Phase 1', $inspection->meeting_point);

        // Verification of lead upgrade to Hot & +20 points for inspection request (ensures Hot threshold 60+)
        $this->lead->refresh();
        $this->assertEquals(60, $this->lead->score);
        $this->assertEquals(LeadTemperature::Hot, $this->lead->temperature);

        // Dispatches events
        Event::assertDispatched(InspectionScheduled::class);
        Event::assertDispatched(InspectionStatusChanged::class);

        // Database record exists
        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'contact_id' => $this->contact->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_prevents_conflicting_representative_assignments(): void
    {
        $date = now()->addDays(3)->format('Y-m-d');
        $time = '10:00 AM';

        // Schedule first inspection
        $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $date,
            'inspection_time' => $time,
        ], $this->creator);

        // Second client attempts to book same representative on same date and time slot
        $secondContact = Contact::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service->scheduleInspection([
            'contact_id' => $secondContact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $date,
            'inspection_time' => $time,
        ], $this->creator);
    }

    public function test_allows_different_times_or_representatives_on_same_date(): void
    {
        $date = now()->addDays(3)->format('Y-m-d');

        // First inspection: 10:00 AM
        $first = $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $date,
            'inspection_time' => '10:00 AM',
        ], $this->creator);

        // Second inspection for same rep on same day but 02:00 PM -> allowed
        $secondContact = Contact::factory()->create();
        $second = $this->service->scheduleInspection([
            'contact_id' => $secondContact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $date,
            'inspection_time' => '02:00 PM',
        ], $this->creator);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_status_transitions_and_domain_events(): void
    {
        Event::fake([
            InspectionConfirmed::class,
            InspectionCompleted::class,
            InspectionCancelled::class,
            InspectionNoShow::class,
            InspectionStatusChanged::class,
        ]);

        $inspection = $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'inspection_date' => now()->addDays(1)->format('Y-m-d'),
            'inspection_time' => '10:00 AM',
        ]);

        // 1. Confirm
        $inspection = $this->service->updateStatus($inspection, InspectionStatus::Confirmed, [], $this->creator);
        $this->assertEquals(InspectionStatus::Confirmed, $inspection->status);
        Event::assertDispatched(InspectionConfirmed::class);

        // 2. Complete & record outcome -> awards +20 inspection_completed points
        $scoreBeforeComplete = $this->lead->fresh()->score;
        $inspection = $this->service->updateStatus($inspection, InspectionStatus::Completed, [
            'outcome' => 'Client loved the lake view, agreed to proceed with 3-bedroom unit deposit.',
        ], $this->creator);

        $this->assertEquals(InspectionStatus::Completed, $inspection->status);
        $this->assertStringContainsString('Client loved the lake view', $inspection->outcome);
        Event::assertDispatched(InspectionCompleted::class);

        // Inspection completed scoring triggered (+20 points)
        $this->assertEquals($scoreBeforeComplete + 20, $this->lead->fresh()->score);
    }

    public function test_reschedule_inspection_validates_conflicts_and_dispatches_event(): void
    {
        Event::fake([InspectionRescheduled::class]);

        $initialDate = now()->addDays(2)->format('Y-m-d');
        $newDate = now()->addDays(5)->format('Y-m-d');

        $inspection = $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $initialDate,
            'inspection_time' => '10:00 AM',
        ]);

        // Reschedule to new date
        $rescheduled = $this->service->reschedule(
            $inspection,
            $newDate,
            '02:00 PM',
            'Customer flight delayed, moved to afternoon',
            null,
            $this->creator
        );

        $this->assertEquals(InspectionStatus::Rescheduled, $rescheduled->status);
        $this->assertEquals($newDate, $rescheduled->inspection_date->format('Y-m-d'));
        $this->assertEquals('02:00 PM', $rescheduled->inspection_time);
        $this->assertStringContainsString('Customer flight delayed', $rescheduled->sales_notes);

        Event::assertDispatched(InspectionRescheduled::class, function (InspectionRescheduled $event) use ($inspection, $initialDate, $newDate) {
            return $event->inspection->id === $inspection->id
                && $event->oldDate === $initialDate
                && $event->oldTime === '10:00 AM'
                && $event->newDate === $newDate
                && $event->newTime === '02:00 PM';
        });
    }

    public function test_assign_representative_prevents_conflict(): void
    {
        $date = now()->addDays(4)->format('Y-m-d');
        $time = '10:00 AM';

        // Representative already booked
        $existing = $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->representative->id,
            'inspection_date' => $date,
            'inspection_time' => $time,
        ]);

        // Second unassigned inspection on same date & time
        $secondContact = Contact::factory()->create();
        $unassigned = $this->service->scheduleInspection([
            'contact_id' => $secondContact->id,
            'representative_id' => null,
            'inspection_date' => $date,
            'inspection_time' => $time,
        ]);

        // Attempting to assign representative to the second inspection throws conflict
        $this->expectException(ValidationException::class);
        $this->service->assignRepresentative($unassigned, $this->representative, $this->creator);
    }

    public function test_get_calendar_events_returns_formatted_data(): void
    {
        $date = now()->addDays(2)->format('Y-m-d');

        $this->service->scheduleInspection([
            'contact_id' => $this->contact->id,
            'representative_id' => $this->representative->id,
            'estate_name' => 'Atlantic Breeze Estate',
            'inspection_date' => $date,
            'inspection_time' => '10:00 AM',
        ]);

        $events = $this->service->getCalendarEvents(
            now()->subDay()->toDateString(),
            now()->addDays(10)->toDateString()
        );

        $this->assertNotEmpty($events);
        $event = $events[0];
        $this->assertEquals($date, $event['date']);
        $this->assertEquals('10:00 AM', $event['time']);
        $this->assertEquals('scheduled', $event['status']);
        $this->assertEquals($this->contact->id, $event['contact']['id']);
        $this->assertEquals('Atlantic Breeze Estate', $event['estate_name']);
        $this->assertEquals($this->representative->name, $event['representative']['name']);
    }
}
