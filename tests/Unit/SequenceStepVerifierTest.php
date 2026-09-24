<?php

namespace Tests\Unit;

use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\LeadStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\FollowUpSequence;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Services\Sequence\SequenceStepVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequenceStepVerifierTest extends TestCase
{
    use RefreshDatabase;

    protected SequenceStepVerifier $verifier;

    protected Contact $contact;

    protected Lead $lead;

    protected FollowUpSequence $sequence;

    protected SequenceStep $step;

    protected SequenceEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = app(SequenceStepVerifier::class);

        $this->contact = Contact::factory()->create([
            'first_name' => 'Adewale',
            'status' => ContactStatus::Lead,
            'has_opted_out' => false,
        ]);

        $this->lead = Lead::factory()->create([
            'contact_id' => $this->contact->id,
            'status' => LeadStatus::New,
            'score' => 60,
        ]);

        $this->sequence = FollowUpSequence::factory()->create([
            'status' => SequenceStatus::Active,
            'exit_on_deal_won' => true,
        ]);

        $this->step = SequenceStep::factory()->create([
            'sequence_id' => $this->sequence->id,
            'step_number' => 1,
            'delay_minutes' => 0,
        ]);

        $this->enrollment = SequenceEnrollment::factory()->create([
            'sequence_id' => $this->sequence->id,
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => SequenceEnrollmentStatus::Active,
        ]);
    }

    /**
     * Guardrail 1: Lead remains active.
     */
    public function test_guardrail_1_lead_and_contact_remain_active(): void
    {
        // 1. Initially active -> passes
        $check = $this->verifier->verifyLeadActive($this->lead, $this->contact);
        $this->assertTrue($check['passed']);

        // 2. Inactive contact -> fails terminally
        $this->contact->update(['status' => ContactStatus::Inactive]);
        $checkArchived = $this->verifier->verifyLeadActive($this->lead, $this->contact->fresh());
        $this->assertFalse($checkArchived['passed']);
        $this->assertTrue($checkArchived['terminal']);

        // 3. Lost lead -> fails terminally
        $this->contact->update(['status' => ContactStatus::Lead]);
        $this->lead->update(['status' => LeadStatus::Lost]);
        $checkLost = $this->verifier->verifyLeadActive($this->lead->fresh(), $this->contact->fresh());
        $this->assertFalse($checkLost['passed']);
        $this->assertTrue($checkLost['terminal']);

        // 4. Soft-deleted contact -> fails terminally
        $this->contact->delete();
        $checkDeleted = $this->verifier->verifyLeadActive($this->lead->fresh(), $this->contact);
        $this->assertFalse($checkDeleted['passed']);
        $this->assertTrue($checkDeleted['terminal']);
    }

    /**
     * Guardrail 2: Customer has not opted out.
     */
    public function test_guardrail_2_customer_has_not_opted_out(): void
    {
        // 1. Not opted out -> passes
        $check = $this->verifier->verifyCustomerNotOptedOut($this->contact);
        $this->assertTrue($check['passed']);

        // 2. Opted out -> fails terminally
        $this->contact->update(['has_opted_out' => true]);
        $checkOptedOut = $this->verifier->verifyCustomerNotOptedOut($this->contact->fresh());
        $this->assertFalse($checkOptedOut['passed']);
        $this->assertTrue($checkOptedOut['terminal']);
        $this->assertStringContainsString('opted out', $checkOptedOut['reason']);
    }

    /**
     * Guardrail 3: Deal isn't already won.
     */
    public function test_guardrail_3_deal_is_not_already_won(): void
    {
        // 1. No deals -> passes
        $check = $this->verifier->verifyDealNotWon($this->contact, $this->lead);
        $this->assertTrue($check['passed']);

        // 2. Open / in progress deal -> passes
        Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => DealStatus::Open,
        ]);
        $checkOpen = $this->verifier->verifyDealNotWon($this->contact->fresh(), $this->lead->fresh());
        $this->assertTrue($checkOpen['passed']);

        // 3. Won deal on contact -> fails terminally
        Deal::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => DealStatus::Won,
        ]);
        $checkWon = $this->verifier->verifyDealNotWon($this->contact->fresh(), $this->lead->fresh());
        $this->assertFalse($checkWon['passed']);
        $this->assertTrue($checkWon['terminal']);

        // 4. Lead marked as Won -> fails terminally
        $this->lead->update(['status' => LeadStatus::Won]);
        $checkLeadWon = $this->verifier->verifyDealNotWon($this->contact->fresh(), $this->lead->fresh());
        $this->assertFalse($checkLeadWon['passed']);
        $this->assertTrue($checkLeadWon['terminal']);
    }

    /**
     * Guardrail 4: Sequence hasn't been cancelled.
     */
    public function test_guardrail_4_sequence_has_not_been_cancelled(): void
    {
        // 1. Active sequence and active enrollment -> passes
        $check = $this->verifier->verifySequenceNotCancelled($this->enrollment);
        $this->assertTrue($check['passed']);

        // 2. Cancelled enrollment -> fails terminally
        $this->enrollment->update(['status' => SequenceEnrollmentStatus::Cancelled]);
        $checkCancelled = $this->verifier->verifySequenceNotCancelled($this->enrollment->fresh());
        $this->assertFalse($checkCancelled['passed']);
        $this->assertTrue($checkCancelled['terminal']);

        // 3. Paused sequence -> fails
        $this->enrollment->update(['status' => SequenceEnrollmentStatus::Active]);
        $this->sequence->update(['status' => SequenceStatus::Paused]);
        $checkPaused = $this->verifier->verifySequenceNotCancelled($this->enrollment->fresh(['sequence']));
        $this->assertFalse($checkPaused['passed']);
    }

    /**
     * Guardrail 5: Message remains applicable.
     */
    public function test_guardrail_5_message_remains_applicable(): void
    {
        // 1. No applicability rules configured -> passes unconditionally
        $check = $this->verifier->verifyMessageApplicable($this->step, $this->contact, $this->lead);
        $this->assertTrue($check['passed']);

        // 2. Rule requiring inspection not yet completed
        $this->step->update([
            'applicability_rules' => [
                'requires_inspection_not_completed' => true,
            ],
        ]);
        $checkApplicable = $this->verifier->verifyMessageApplicable($this->step->fresh(), $this->contact, $this->lead);
        $this->assertTrue($checkApplicable['passed']);

        // If completed inspection exists -> fails (non-terminally)
        Inspection::factory()->create([
            'contact_id' => $this->contact->id,
            'lead_id' => $this->lead->id,
            'status' => 'completed',
        ]);
        $checkInapplicable = $this->verifier->verifyMessageApplicable($this->step->fresh(), $this->contact->fresh(), $this->lead->fresh());
        $this->assertFalse($checkInapplicable['passed']);
        $this->assertFalse($checkInapplicable['terminal']); // Non-terminal: skips this step only

        // 3. Minimum lead score rule
        $this->step->update([
            'applicability_rules' => [
                'min_lead_score' => 80,
            ],
        ]);
        // Lead score is 60 -> fails
        $checkScore = $this->verifier->verifyMessageApplicable($this->step->fresh(), $this->contact, $this->lead);
        $this->assertFalse($checkScore['passed']);
        $this->assertFalse($checkScore['terminal']);
    }

    /**
     * Composite: verifyAll runs all 5 guardrails.
     */
    public function test_verify_all_composite_guardrails_pass_and_fail_appropriately(): void
    {
        // 1. Everything clean -> can execute
        $result = $this->verifier->verifyAll($this->enrollment, $this->step);
        $this->assertTrue($result['can_execute']);
        $this->assertFalse($result['should_cancel_sequence']);

        // 2. Contact opts out -> cannot execute, should cancel sequence
        $this->contact->update(['has_opted_out' => true]);
        $resultOptOut = $this->verifier->verifyAll($this->enrollment->fresh(['contact']), $this->step);
        $this->assertFalse($resultOptOut['can_execute']);
        $this->assertTrue($resultOptOut['should_cancel_sequence']);
        $this->assertEquals('not_opted_out', $resultOptOut['failed_check']);
    }
}
