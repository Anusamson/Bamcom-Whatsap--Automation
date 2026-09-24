<?php

namespace App\Services\Sequence;

use App\Enums\ContactStatus;
use App\Enums\DealStatus;
use App\Enums\InspectionStatus;
use App\Enums\LeadStatus;
use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Services\Automation\TriggerEvaluator;

/**
 * Verifier enforcing the 5 pre-execution guardrails for sequence steps:
 * 1. Lead remains active
 * 2. Customer has not opted out
 * 3. Deal isn't already won
 * 4. Sequence hasn't been cancelled
 * 5. Message remains applicable
 */
class SequenceStepVerifier
{
    public function __construct(
        protected TriggerEvaluator $triggerEvaluator
    ) {}

    /**
     * Run all 5 guardrail checks before executing a sequence step.
     *
     * @return array{
     *     can_execute: bool,
     *     should_cancel_sequence: bool,
     *     reason?: string,
     *     checks: array<string, array{passed: bool, reason?: string, terminal: bool}>
     * }
     */
    public function verifyAll(SequenceEnrollment $enrollment, SequenceStep $step): array
    {
        $contact = $enrollment->contact;
        $lead = $enrollment->lead;

        $checks = [
            'lead_active' => $this->verifyLeadActive($lead, $contact),
            'not_opted_out' => $this->verifyCustomerNotOptedOut($contact),
            'deal_not_won' => $this->verifyDealNotWon($contact, $lead),
            'sequence_not_cancelled' => $this->verifySequenceNotCancelled($enrollment),
            'message_applicable' => $this->verifyMessageApplicable($step, $contact, $lead),
        ];

        foreach ($checks as $checkName => $check) {
            if (! $check['passed']) {
                return [
                    'can_execute' => false,
                    'should_cancel_sequence' => $check['terminal'],
                    'reason' => $check['reason'] ?? "Guardrail check '{$checkName}' failed",
                    'failed_check' => $checkName,
                    'checks' => $checks,
                ];
            }
        }

        return [
            'can_execute' => true,
            'should_cancel_sequence' => false,
            'checks' => $checks,
        ];
    }

    /**
     * 1. Verify lead and contact remain active.
     *
     * @return array{passed: bool, reason?: string, terminal: bool}
     */
    public function verifyLeadActive(?Lead $lead, Contact $contact): array
    {
        if ($contact->trashed()) {
            return [
                'passed' => false,
                'reason' => 'Contact record has been deleted or archived.',
                'terminal' => true,
            ];
        }

        if ($contact->status === ContactStatus::Inactive || $contact->status === ContactStatus::Dormant) {
            return [
                'passed' => false,
                'reason' => "Contact is {$contact->status->label()}.",
                'terminal' => true,
            ];
        }

        if ($lead) {
            if ($lead->trashed()) {
                return [
                    'passed' => false,
                    'reason' => 'Associated lead record has been deleted.',
                    'terminal' => true,
                ];
            }

            if ($lead->status === LeadStatus::Lost || $lead->status === LeadStatus::Disqualified) {
                return [
                    'passed' => false,
                    'reason' => "Lead is no longer active (status: {$lead->status->label()}).",
                    'terminal' => true,
                ];
            }
        }

        return ['passed' => true, 'terminal' => false];
    }

    /**
     * 2. Verify customer has not opted out.
     *
     * @return array{passed: bool, reason?: string, terminal: bool}
     */
    public function verifyCustomerNotOptedOut(Contact $contact): array
    {
        if ($contact->has_opted_out) {
            return [
                'passed' => false,
                'reason' => 'Customer has explicitly opted out of automated sequences (Reason: '.($contact->opt_out_reason ?? 'Unspecified').').',
                'terminal' => true,
            ];
        }

        if ($contact->hasTag('opt_out') || $contact->hasTag('dnd')) {
            return [
                'passed' => false,
                'reason' => 'Customer possesses an opt-out or DND tag.',
                'terminal' => true,
            ];
        }

        return ['passed' => true, 'terminal' => false];
    }

    /**
     * 3. Verify deal isn't already closed won.
     *
     * @return array{passed: bool, reason?: string, terminal: bool}
     */
    public function verifyDealNotWon(Contact $contact, ?Lead $lead): array
    {
        // Check contact's deals
        $hasWonDeal = $contact->deals()
            ->where('status', DealStatus::Won->value)
            ->exists();

        if ($hasWonDeal) {
            return [
                'passed' => false,
                'reason' => 'Customer already has a deal closed won.',
                'terminal' => true,
            ];
        }

        if ($lead) {
            if ($lead->status === LeadStatus::Won) {
                return [
                    'passed' => false,
                    'reason' => 'Lead status is already marked as won.',
                    'terminal' => true,
                ];
            }

            if ($lead->deals()->where('status', DealStatus::Won->value)->exists()) {
                return [
                    'passed' => false,
                    'reason' => 'Lead deal has already been closed won.',
                    'terminal' => true,
                ];
            }
        }

        return ['passed' => true, 'terminal' => false];
    }

    /**
     * 4. Verify sequence hasn't been cancelled or paused.
     *
     * @return array{passed: bool, reason?: string, terminal: bool}
     */
    public function verifySequenceNotCancelled(SequenceEnrollment $enrollment): array
    {
        if ($enrollment->status !== SequenceEnrollmentStatus::Active) {
            return [
                'passed' => false,
                'reason' => "Sequence enrollment is {$enrollment->status->label()} (not active).",
                'terminal' => $enrollment->status === SequenceEnrollmentStatus::Cancelled || $enrollment->status === SequenceEnrollmentStatus::Completed,
            ];
        }

        $sequence = $enrollment->sequence;
        if (! $sequence || ! $sequence->is_active || $sequence->status !== SequenceStatus::Active || $sequence->trashed()) {
            return [
                'passed' => false,
                'reason' => 'The parent sequence has been paused, deactivated, or deleted.',
                'terminal' => $sequence ? $sequence->trashed() : true,
            ];
        }

        return ['passed' => true, 'terminal' => false];
    }

    /**
     * 5. Verify message remains applicable.
     *
     * @return array{passed: bool, reason?: string, terminal: bool}
     */
    public function verifyMessageApplicable(SequenceStep $step, Contact $contact, ?Lead $lead): array
    {
        // If step has no WhatsApp message, this check passes by default
        if (! $step->hasWhatsApp()) {
            return ['passed' => true, 'terminal' => false];
        }

        // Custom applicability rules evaluation
        if (! empty($step->applicability_rules)) {
            $rules = $step->applicability_rules;

            if (isset($rules['min_lead_score'])) {
                if (! $lead || $lead->score < (int) $rules['min_lead_score']) {
                    return [
                        'passed' => false,
                        'reason' => "Lead score ({$lead?->score}) is below required minimum ({$rules['min_lead_score']}).",
                        'terminal' => false,
                    ];
                }
            }

            if (! empty($rules['requires_inspection_not_completed'])) {
                $hasCompleted = Inspection::where('contact_id', $contact->id)
                    ->where('status', InspectionStatus::Completed->value)
                    ->exists();

                if ($hasCompleted) {
                    return [
                        'passed' => false,
                        'reason' => 'Customer has already completed an inspection.',
                        'terminal' => false,
                    ];
                }
            }

            if (isset($rules['conditions']) || (is_array($rules) && isset($rules[0]) && (is_array($rules[0]) || is_object($rules[0])))) {
                $conditions = $rules['conditions'] ?? $rules;
                $objConditions = collect($conditions)->map(fn ($c) => (object) $c);
                $subject = $lead ?? $contact;
                $passed = $this->triggerEvaluator->evaluateConditions($objConditions, $subject);

                if (! $passed) {
                    return [
                        'passed' => false,
                        'reason' => 'Step custom applicability conditions are no longer met.',
                        'terminal' => false,
                    ];
                }
            }
        }

        // Inspection contextual applicability: if message text is about inspection, check active inspection
        $messageText = (string) ($step->whatsapp_config['message'] ?? $step->whatsapp_config['template_name'] ?? '');
        if (str_contains(mb_strtolower($messageText), 'inspection')) {
            // Check if there was an inspection cancelled or if cancelled recently
            $hasCancelledInspection = Inspection::where('contact_id', $contact->id)
                ->where('status', InspectionStatus::Cancelled->value)
                ->where('updated_at', '>=', now()->subDays(7))
                ->exists();

            $hasActiveInspection = Inspection::where('contact_id', $contact->id)
                ->whereIn('status', [InspectionStatus::Scheduled->value, InspectionStatus::Confirmed->value])
                ->exists();

            // If message is a pre-inspection reminder but no active inspection exists, message is no longer applicable
            if (str_contains(mb_strtolower($messageText), 'reminder') && ! $hasActiveInspection) {
                return [
                    'passed' => false,
                    'reason' => 'Pre-inspection reminder message is no longer applicable because no active inspection is scheduled.',
                    'terminal' => false,
                ];
            }
        }

        return ['passed' => true, 'terminal' => false];
    }
}
