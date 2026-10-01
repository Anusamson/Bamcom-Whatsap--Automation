<?php

namespace Tests\Feature\Email;

use App\Enums\AutomationActionType;
use App\Enums\AutomationTriggerType;
use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\EmailMarketingStatus;
use App\Enums\EmailSuppressionReason;
use App\Enums\LeadStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Events\CustomerUnresponsive;
use App\Events\LeadCreated;
use App\Events\LeadQualified;
use App\Events\PropertyInterestAdded;
use App\Jobs\ExecuteSequenceStepJob;
use App\Models\AutomationAction;
use App\Models\AutomationTrigger;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\EmailMessage;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Property;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Models\SequenceStepLog;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use App\Services\Sequence\SequenceService;
use App\Services\Sequence\SequenceStepVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailAutomationSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Contact $contact;

    protected Lead $lead;

    protected SequenceService $sequenceService;

    protected AutomationEngine $automationEngine;

    protected SequenceStepVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'status' => 'active',
            'name' => 'Agent Samson',
        ]);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Chinedu',
            'last_name' => 'Okafor',
            'email' => 'chinedu.okafor@example.com',
            'phone' => '+2348022334455',
            'status' => ContactStatus::Lead,
            'email_marketing_status' => EmailMarketingStatus::Subscribed,
            'has_opted_out' => false,
            'assigned_user_id' => $this->user->id,
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'assigned_user_id' => $this->user->id,
            'status' => LeadStatus::New,
            'score' => 75,
        ]);

        $this->sequenceService = app(SequenceService::class);
        $this->automationEngine = app(AutomationEngine::class);
        $this->verifier = app(SequenceStepVerifier::class);
    }

    /**
     * Test SEND_EMAIL action executes via AutomationEngine and records sent email.
     */
    public function test_send_email_action_executes_successfully_via_automation_engine(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Direct Email Workflow',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::Manual,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [
                'subject' => 'Exclusive Luxury Villa in Lekki Phase 1',
                'body_html' => '<p>Hello {{contact.first_name}}, check out this new waterfront property.</p>',
                'body_plain' => 'Hello {{contact.first_name}}, check out this new waterfront property.',
            ],
            'delay_seconds' => 0,
        ]);

        $this->automationEngine->dispatch('manual', $this->contact);

        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'to_email' => 'chinedu.okafor@example.com',
            'subject' => 'Exclusive Luxury Villa in Lekki Phase 1',
        ]);

        $email = EmailMessage::where('contact_id', $this->contact->id)->latest()->first();
        $this->assertNotNull($email);
        $this->assertStringContainsString('Hello Chinedu', $email->body_html);
    }

    /**
     * Test SEND_EMAIL_TEMPLATE action renders dynamic variables and sends email.
     */
    public function test_send_email_template_action_renders_variables_and_sends(): void
    {
        $template = EmailTemplate::factory()->create([
            'name' => 'VIP Property Alert',
            'subject' => 'Special Update for {{contact.first_name}}',
            'body_html' => '<div><h3>Welcome {{contact.first_name}}</h3><p>Assigned Representative: {{agent.name}}</p><p><a href="{{unsubscribe_url}}">Unsubscribe</a></p></div>',
            'category' => 'marketing',
            'status' => 'active',
        ]);

        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Template Email Workflow',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::Manual,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmailTemplate,
            'action_config' => [
                'email_template_id' => $template->id,
            ],
            'delay_seconds' => 0,
        ]);

        $this->automationEngine->dispatch('manual', $this->contact);

        $email = EmailMessage::where('email_template_id', $template->id)->latest()->first();
        $this->assertNotNull($email);
        $this->assertEquals('Special Update for Chinedu', $email->subject);
        $this->assertStringContainsString('Welcome Chinedu', $email->body_html);
        $this->assertStringContainsString('Agent Samson', $email->body_html);
    }

    /**
     * Test START_EMAIL_SEQUENCE action enrolls contact into sequence.
     */
    public function test_start_email_sequence_action_enrolls_contact_into_sequence(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create([
            'name' => 'Email Onboarding Sequence',
            'status' => SequenceStatus::Active,
        ]);

        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Enroll in Sequence Workflow',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::Manual,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::StartEmailSequence,
            'action_config' => [
                'sequence_id' => $sequence->id,
            ],
            'delay_seconds' => 0,
        ]);

        $this->automationEngine->dispatch('manual', $this->contact);

        $this->assertDatabaseHas('sequence_enrollments', [
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active->value,
        ]);
    }

    /**
     * Test STOP_EMAIL_SEQUENCE action cancels active enrollments.
     */
    public function test_stop_email_sequence_action_cancels_active_enrollments(): void
    {
        $sequence = FollowUpSequence::factory()->create([
            'name' => 'Active Email Sequence',
            'status' => SequenceStatus::Active,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Stop Sequence Workflow',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::Manual,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::StopEmailSequence,
            'action_config' => [
                'sequence_id' => $sequence->id,
                'reason' => 'Target converted',
            ],
            'delay_seconds' => 0,
        ]);

        $this->automationEngine->dispatch('manual', $this->contact);

        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollment->fresh()->status);
        $this->assertEquals('Target converted', $enrollment->fresh()->cancellation_reason);
    }

    /**
     * Test Sequence step with email template executes and sends email.
     */
    public function test_sequence_step_with_email_template_executes_and_sends_email(): void
    {
        Queue::fake();

        $template = EmailTemplate::factory()->create([
            'name' => 'Sequence Step 1 Template',
            'subject' => 'Day 1: Hello {{contact.first_name}}',
            'body_html' => '<p>Greetings {{contact.first_name}}, thank you for contacting Bamcom Properties!</p>',
        ]);

        $sequence = FollowUpSequence::factory()->create([
            'name' => 'Property Buyer 3-Step Sequence',
            'status' => SequenceStatus::Active,
        ]);

        $step1 = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_unit' => 'minutes',
            'delay_value' => 0,
            'delay_minutes' => 0,
            'email_template_id' => $template->id,
            'email_config' => [
                'email_template_id' => $template->id,
            ],
        ]);

        $step2 = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 2,
            'delay_unit' => 'days',
            'delay_value' => 2,
            'delay_minutes' => 2880,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
            'current_step_number' => 0,
        ]);

        // Execute step 1 directly via sequence service
        $this->sequenceService->executeStep($enrollment, $step1);

        // Verify email sent
        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'email_template_id' => $template->id,
            'subject' => 'Day 1: Hello Chinedu',
        ]);

        // Verify step log completed
        $this->assertDatabaseHas('sequence_step_logs', [
            'sequence_enrollment_id' => $enrollment->id,
            'sequence_step_id' => $step1->id,
            'status' => 'completed',
        ]);

        // Verify enrollment advanced to step 2
        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(1, $enrollmentFresh->current_step_number);
        $this->assertEquals($step2->id, $enrollmentFresh->next_step_id);

        Queue::assertPushed(ExecuteSequenceStepJob::class);
    }

    /**
     * Test delay unit calculation supporting minutes, hours, days, weeks.
     */
    public function test_delay_unit_conversions_calculate_correct_minutes(): void
    {
        $minutes = $this->sequenceService->calculateDelayMinutes(45, 'minutes');
        $hours = $this->sequenceService->calculateDelayMinutes(3, 'hours');
        $days = $this->sequenceService->calculateDelayMinutes(2, 'days');
        $weeks = $this->sequenceService->calculateDelayMinutes(1, 'weeks');

        $this->assertEquals(45, $minutes);
        $this->assertEquals(180, $hours);
        $this->assertEquals(2880, $days);
        $this->assertEquals(10080, $weeks);
    }

    /**
     * Test sequence guardrail: auto-cancels sequence when contact unsubscribes.
     */
    public function test_sequence_guardrail_auto_cancels_when_contact_unsubscribes(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        // Contact unsubscribes from email marketing
        $this->contact->update([
            'email_marketing_status' => EmailMarketingStatus::Unsubscribed,
        ]);

        $this->sequenceService->executeStep($enrollment, $step);

        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollmentFresh->status);
        $this->assertStringContainsString('unsubscribed', strtolower((string) $enrollmentFresh->cancellation_reason));
        $this->assertEquals(0, EmailMessage::where('contact_id', $this->contact->id)->count());
    }

    /**
     * Test sequence guardrail: auto-cancels sequence when contact email is suppressed.
     */
    public function test_sequence_guardrail_auto_cancels_when_email_is_suppressed(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        // Contact email added to suppression list
        EmailSuppression::create([
            'email' => $this->contact->email,
            'reason' => EmailSuppressionReason::Complaint,
            'source' => 'spam_complaint',
            'suppressed_at' => now(),
        ]);

        $this->sequenceService->executeStep($enrollment, $step);

        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollmentFresh->status);
        $this->assertStringContainsString('suppressed', strtolower((string) $enrollmentFresh->cancellation_reason));
        $this->assertEquals(0, EmailMessage::where('contact_id', $this->contact->id)->count());
    }

    /**
     * Test sequence guardrail: auto-cancels sequence when deal is won.
     */
    public function test_sequence_guardrail_auto_cancels_when_deal_is_won(): void
    {
        $sequence = FollowUpSequence::factory()->create([
            'status' => SequenceStatus::Active,
            'exit_on_deal_won' => true,
        ]);

        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        // Deal is won for this contact
        Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => DealStatus::Won,
            'deal_value' => 85000000,
        ]);

        $this->sequenceService->executeStep($enrollment, $step);

        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollmentFresh->status);
        $this->assertStringContainsString('won', strtolower((string) $enrollmentFresh->cancellation_reason));
        $this->assertEquals(0, EmailMessage::where('contact_id', $this->contact->id)->count());
    }

    /**
     * Test sequence guardrail: auto-cancels sequence when lead enters incompatible Lost state.
     */
    public function test_sequence_guardrail_auto_cancels_when_lead_enters_lost_state(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Active]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        // Lead marked as Lost
        $this->lead->update(['status' => LeadStatus::Lost]);

        $this->sequenceService->executeStep($enrollment, $step);

        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Cancelled, $enrollmentFresh->status);
        $this->assertStringContainsString('active', strtolower((string) $enrollmentFresh->cancellation_reason));
        $this->assertEquals(0, EmailMessage::where('contact_id', $this->contact->id)->count());
    }

    /**
     * Test sequence guardrail: halts execution when sequence is paused.
     */
    public function test_sequence_guardrail_halts_when_sequence_is_paused(): void
    {
        $sequence = FollowUpSequence::factory()->create(['status' => SequenceStatus::Paused]);
        $step = SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);

        $this->sequenceService->executeStep($enrollment, $step);

        // Enrollment should NOT be cancelled, but step was NOT executed
        $enrollmentFresh = $enrollment->fresh();
        $this->assertEquals(SequenceEnrollmentStatus::Active, $enrollmentFresh->status);
        $this->assertEquals(0, SequenceStepLog::where('sequence_enrollment_id', $enrollment->id)->count());
        $this->assertEquals(0, EmailMessage::where('contact_id', $this->contact->id)->count());
    }

    /**
     * Test CRM LeadCreated event triggers automation workflow.
     */
    public function test_crm_lead_created_event_triggers_email_workflow(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Welcome New Lead by Email',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadCreated,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [
                'subject' => 'Welcome to Bamcom Real Estate',
                'body_html' => '<p>Thank you for expressing interest in our properties.</p>',
            ],
            'delay_seconds' => 0,
        ]);

        // Dispatch domain event
        LeadCreated::dispatch($this->lead);

        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'subject' => 'Welcome to Bamcom Real Estate',
        ]);
    }

    /**
     * Test CRM LeadQualified event triggers automation workflow.
     */
    public function test_crm_lead_qualified_event_triggers_email_workflow(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Qualified Lead Consultation Offer',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::LeadQualified,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [
                'subject' => 'You Qualify for Preferred Buyer Financing',
                'body_html' => '<p>Dear {{contact.first_name}}, our advisory team is ready.</p>',
            ],
            'delay_seconds' => 0,
        ]);

        // Dispatch domain event
        LeadQualified::dispatch($this->lead);

        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'subject' => 'You Qualify for Preferred Buyer Financing',
        ]);
    }

    /**
     * Test CRM PropertyInterestAdded event triggers automation workflow.
     */
    public function test_crm_property_interest_added_event_triggers_email_workflow(): void
    {
        $property = Property::factory()->create([
            'title' => 'Eko Atlantic Penthouse Suite',
        ]);

        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Property Brochure Delivery',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::PropertyInterestAdded,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [
                'subject' => 'Brochure for Eko Atlantic Penthouse Suite',
                'body_html' => '<p>Attached is the brochure for your selected property.</p>',
            ],
            'delay_seconds' => 0,
        ]);

        // Dispatch domain event
        PropertyInterestAdded::dispatch($this->contact, $property, $this->lead);

        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'subject' => 'Brochure for Eko Atlantic Penthouse Suite',
        ]);
    }

    /**
     * Test CRM CustomerUnresponsive event triggers re-engagement email workflow.
     */
    public function test_crm_customer_unresponsive_event_triggers_email_workflow(): void
    {
        $workflow = AutomationWorkflow::factory()->create([
            'name' => 'Customer Re-engagement Nudge',
            'is_active' => true,
            'status' => 'active',
        ]);

        AutomationTrigger::factory()->create([
            'workflow_id' => $workflow->id,
            'trigger_type' => AutomationTriggerType::CustomerUnresponsive,
            'is_active' => true,
        ]);

        AutomationAction::factory()->create([
            'workflow_id' => $workflow->id,
            'action_type' => AutomationActionType::SendEmail,
            'action_config' => [
                'subject' => 'Are you still looking for a property in Lagos?',
                'body_html' => '<p>We noticed you haven\'t been active recently.</p>',
            ],
            'delay_seconds' => 0,
        ]);

        // Dispatch domain event
        CustomerUnresponsive::dispatch($this->contact, $this->lead, 14);

        $this->assertDatabaseHas('email_messages', [
            'contact_id' => $this->contact->id,
            'subject' => 'Are you still looking for a property in Lagos?',
        ]);
    }

    /**
     * Test duplicate sequence enrollment prevention.
     */
    public function test_duplicate_sequence_enrollment_prevention(): void
    {
        Queue::fake();

        $sequence = FollowUpSequence::factory()->create([
            'name' => 'Single Enrollment Guard Sequence',
            'status' => SequenceStatus::Active,
        ]);

        SequenceStep::factory()->create([
            'sequence_id' => $sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        // First enrollment succeeds
        $enrollment1 = $this->sequenceService->enroll($this->contact, $sequence, $this->lead);
        $this->assertInstanceOf(SequenceEnrollment::class, $enrollment1);

        // Attempting to enroll again into same active sequence returns existing enrollment
        $enrollment2 = $this->sequenceService->enroll($this->contact, $sequence, $this->lead);
        $this->assertEquals($enrollment1->id, $enrollment2->id);

        $this->assertEquals(
            1,
            SequenceEnrollment::where('contact_id', $this->contact->id)
                ->where('sequence_id', $sequence->id)
                ->where('status', SequenceEnrollmentStatus::Active->value)
                ->count()
        );
    }
}
