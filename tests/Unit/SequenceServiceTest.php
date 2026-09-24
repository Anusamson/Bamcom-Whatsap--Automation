<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Jobs\ExecuteSequenceStepJob;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\SequenceStepLog;
use App\Models\Task;
use App\Models\User;
use App\Services\Sequence\SequenceService;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class SequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SequenceService $service;

    protected Contact $contact;

    protected Lead $lead;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock WhatsApp Client
        $mockClient = Mockery::mock(WhatsAppClient::class);
        $mockClient->shouldReceive('sendTextMessage')
            ->byDefault()
            ->andReturn([
                'messages' => [['id' => 'wamid.test_seq_'.uniqid()]],
                'contacts' => [['input' => '2348011223344', 'wa_id' => '2348011223344']],
            ]);
        $this->app->instance(WhatsAppClient::class, $mockClient);

        $this->service = app(SequenceService::class);

        $this->user = User::factory()->create();

        $this->contact = Contact::factory()->create([
            'first_name' => 'Chioma',
            'last_name' => 'Okonkwo',
            'phone' => '+2348011223344',
            'status' => ContactStatus::Lead,
            'has_opted_out' => false,
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'score' => 60,
        ]);
    }

    /**
     * Admin creates multi-step sequence with actions.
     */
    public function test_creates_multi_step_sequence_with_actions(): void
    {
        $pipeline = Pipeline::factory()->create();
        $stage = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id]);

        $sequence = $this->service->createSequence([
            'name' => 'High-Intent Investor Cadence',
            'description' => 'Multi-step follow-up for property investors',
            'status' => SequenceStatus::Active,
            'exit_on_deal_won' => true,
            'steps' => [
                [
                    'step_number' => 1,
                    'name' => 'Day 0 Welcome',
                    'delay_minutes' => 0,
                    'whatsapp_config' => ['message' => 'Hello {{contact.first_name}}, welcome to Bamcom!'],
                    'task_config' => ['title' => 'Follow up with {{contact.first_name}}', 'due_in_days' => 1],
                    'tag_config' => ['add_tags' => ['vip-lead']],
                ],
                [
                    'step_number' => 2,
                    'name' => 'Day 2 Check-in',
                    'delay_minutes' => 2880,
                    'whatsapp_config' => ['message' => 'Hi {{contact.first_name}}, have you reviewed our listings?'],
                    'stage_change_config' => ['stage_id' => $stage->id],
                ],
            ],
        ], $this->user);

        $this->assertDatabaseHas('follow_up_sequences', [
            'id' => $sequence->id,
            'name' => 'High-Intent Investor Cadence',
            'exit_on_deal_won' => 1,
        ]);

        $this->assertCount(2, $sequence->steps);
        $this->assertEquals(0, $sequence->steps[0]->delay_minutes);
        $this->assertEquals(2880, $sequence->steps[1]->delay_minutes);
    }

    /**
     * Contact enters sequence and dispatches queued execution.
     */
    public function test_contact_enrolls_into_sequence_and_dispatches_first_step(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = $this->service->enroll($this->contact, $sequence, $this->lead, $this->user);

        $this->assertDatabaseHas('sequence_enrollments', [
            'id' => $enrollment->id,
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active->value,
            'next_step_id' => $step->id,
        ]);

        Queue::assertPushed(ExecuteSequenceStepJob::class, function ($job) use ($enrollment, $step): bool {
            return $job->enrollmentId === $enrollment->id && $job->stepId === $step->id;
        });
    }

    /**
     * Opted-out contact cannot be enrolled.
     */
    public function test_cannot_enroll_opted_out_contact(): void
    {
        $this->contact->update(['has_opted_out' => true]);
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('opted out');

        $this->service->enroll($this->contact, $sequence);
    }

    /**
     * Prevent duplicate active enrollment.
     */
    public function test_prevents_duplicate_active_enrollment(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        SequenceStep::factory()->create(['sequence_id' => $sequence->id]);

        $enrollment1 = $this->service->enroll($this->contact, $sequence, $this->lead);
        $enrollment2 = $this->service->enroll($this->contact, $sequence, $this->lead);

        $this->assertEquals($enrollment1->id, $enrollment2->id);
        $this->assertEquals(1, SequenceEnrollment::where('contact_id', $this->contact->id)->where('sequence_id', $sequence->id)->count());
    }

    /**
     * Executes step actions, verifies guardrails, and creates logs.
     */
    public function test_executes_step_with_all_actions_and_logs(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step1 = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
            'whatsapp_config' => ['message' => 'Hello {{contact.first_name}}!'],
            'task_config' => ['title' => 'Call {{contact.first_name}}', 'due_in_days' => 1],
            'tag_config' => ['add_tags' => ['interested-buyer']],
        ]);
        $step2 = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 2,
            'delay_minutes' => 1440,
            'whatsapp_config' => ['message' => 'Follow up on inspection.'],
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $this->service->executeStep($enrollment, $step1);

        // 1. Verify WhatsApp message was logged
        $this->assertDatabaseHas('whatsapp_messages', [
            'contact_id' => $this->contact->id,
            'body' => 'Hello Chioma!',
        ]);

        // 2. Verify Task was created
        $this->assertDatabaseHas('tasks', [
            'contact_id' => $this->contact->id,
            'title' => 'Call Chioma',
        ]);

        // 3. Verify Tag was added
        $this->assertTrue($this->contact->fresh()->hasTag('interested-buyer'));

        // 4. Verify SequenceStepLog was recorded
        $this->assertDatabaseHas('sequence_step_logs', [
            'sequence_enrollment_id' => $enrollment->id,
            'sequence_step_id' => $step1->id,
            'status' => 'completed',
        ]);

        // 5. Verify enrollment was advanced to next step
        $this->assertEquals(1, $enrollment->fresh()->current_step_number);
        $this->assertEquals($step2->id, $enrollment->fresh()->next_step_id);

        // 6. Verify next step job was dispatched with delay
        Queue::assertPushed(ExecuteSequenceStepJob::class, function ($job) use ($enrollment, $step2): bool {
            return $job->enrollmentId === $enrollment->id && $job->stepId === $step2->id;
        });
    }

    /**
     * Terminal guardrail failure (Deal Won) cancels enrollment before step execution.
     */
    public function test_guardrail_failure_cancels_enrollment_before_execution(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'whatsapp_config' => ['message' => 'Should never be sent.'],
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        // Create Won Deal -> guardrail 3 will fail
        Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'status' => DealStatus::Won,
        ]);

        $this->service->executeStep($enrollment, $step);

        // Verify enrollment is cancelled
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->cancelled_at);

        // Verify step was logged as skipped
        $this->assertDatabaseHas('sequence_step_logs', [
            'sequence_enrollment_id' => $enrollment->id,
            'sequence_step_id' => $step->id,
            'status' => 'skipped',
        ]);

        // Verify message was never sent
        $this->assertDatabaseMissing('whatsapp_messages', [
            'contact_id' => $this->contact->id,
            'body' => 'Should never be sent.',
        ]);
    }

    /**
     * Contact can leave sequence manually (unenroll).
     */
    public function test_contact_leaves_sequence_manually(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $this->service->unenroll($enrollment, 'Client requested phone call instead', $this->user);

        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertEquals('Client requested phone call instead', $enrollment->fresh()->cancellation_reason);
    }

    /**
     * Reaching end of sequence marks enrollment as Completed.
     */
    public function test_completes_sequence_when_last_step_finishes(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $onlyStep = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $this->service->executeStep($enrollment, $onlyStep);

        $this->assertEquals(SequenceEnrollmentStatus::Completed, $enrollment->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->assertNull($enrollment->fresh()->next_step_id);
    }
}
