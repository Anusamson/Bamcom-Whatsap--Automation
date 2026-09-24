<?php

namespace Tests\Feature\Sequences;

use App\Enums\DealStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Events\DealWon;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SequenceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
        ]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Femi',
            'last_name' => 'Adeyemi',
            'phone' => '+2348033445566',
            'has_opted_out' => false,
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 70,
        ]);
    }

    /**
     * User can view sequences listing.
     */
    public function test_user_can_view_sequences_index(): void
    {
        FollowUpSequence::factory()->count(3)->create();

        $response = $this->actingAs($this->user)->get(route('sequences.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Sequences/Index'));
    }

    /**
     * User can create a new sequence with steps.
     */
    public function test_user_can_create_sequence_with_steps(): void
    {
        Queue::fake();

        $payload = [
            'name' => 'New Lead 3-Day Nurture',
            'description' => 'Automatic WhatsApp check-ins for new website leads',
            'status' => 'active',
            'trigger_type' => 'manual',
            'exit_on_deal_won' => true,
            'exit_on_reply' => false,
            'steps' => [
                [
                    'step_number' => 1,
                    'name' => 'Instant Welcome',
                    'delay_minutes' => 0,
                    'whatsapp_config' => ['message' => 'Hello {{contact.first_name}}!'],
                    'task_config' => ['title' => 'Initial Call', 'due_in_days' => 1],
                ],
                [
                    'step_number' => 2,
                    'name' => 'Day 1 Follow-up',
                    'delay_minutes' => 1440,
                    'whatsapp_config' => ['message' => 'Checking in on your property search!'],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('sequences.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('follow_up_sequences', [
            'name' => 'New Lead 3-Day Nurture',
            'status' => SequenceStatus::Active->value,
            'exit_on_deal_won' => 1,
        ]);

        $sequence = FollowUpSequence::where('name', 'New Lead 3-Day Nurture')->first();
        $this->assertCount(2, $sequence->steps);
    }

    /**
     * User can toggle active/paused status of a sequence.
     */
    public function test_user_can_toggle_sequence_status(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);

        $response = $this->actingAs($this->user)->post(route('sequences.toggle', $sequence));

        $response->assertRedirect();
        $this->assertEquals(SequenceStatus::Paused, $sequence->fresh()->status);

        // Toggle back to active
        $this->actingAs($this->user)->post(route('sequences.toggle', $sequence));
        $this->assertEquals(SequenceStatus::Active, $sequence->fresh()->status);
    }

    /**
     * User can delete sequence.
     */
    public function test_user_can_delete_sequence(): void
    {
        $sequence = FollowUpSequence::factory()->create();
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $response = $this->actingAs($this->user)->delete(route('sequences.destroy', $sequence));

        $response->assertRedirect(route('sequences.index'));
        $this->assertSoftDeleted('follow_up_sequences', ['id' => $sequence->id]);
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
    }

    /**
     * User can enroll contact into sequence via HTTP.
     */
    public function test_user_can_enroll_contact_into_sequence(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $response = $this->actingAs($this->user)->post(route('sequences.enroll', $sequence), [
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sequence_enrollments', [
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active->value,
        ]);
    }

    /**
     * User can unenroll contact from sequence via HTTP.
     */
    public function test_user_can_unenroll_contact_from_sequence(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $response = $this->actingAs($this->user)->post(route('sequences.enrollments.unenroll', $enrollment), [
            'reason' => 'Customer requested direct email instead',
        ]);

        $response->assertRedirect();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertEquals('Customer requested direct email instead', $enrollment->fresh()->cancellation_reason);
    }

    /**
     * Customer opt-out endpoint cancels all active sequences for that contact.
     */
    public function test_contact_opt_out_cancels_all_active_sequences(): void
    {
        $sequence1 = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $sequence2 = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);

        $enrollment1 = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence1->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $enrollment2 = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence2->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $response = $this->actingAs($this->user)->post(route('contacts.opt-out', $this->contact), [
            'reason' => 'Unsubscribed via WhatsApp STOP command',
        ]);

        $response->assertRedirect();
        $this->assertTrue((bool) $this->contact->fresh()->has_opted_out);
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment1->fresh()->status);
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment2->fresh()->status);
    }

    /**
     * DealWon event triggers automatic exit from active sequences when exit_on_deal_won is enabled.
     */
    public function test_deal_won_event_triggers_automatic_sequence_exit(): void
    {
        $sequence = FollowUpSequence::factory()->create([
            'status' => SequenceStatus::Active,
            'exit_on_deal_won' => true,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $deal = Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => DealStatus::Won,
            'deal_value' => 50000000,
        ]);

        // Dispatch DealWon domain event
        event(new DealWon($deal));

        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertStringContainsString('was won', (string) $enrollment->fresh()->cancellation_reason);
    }
}
