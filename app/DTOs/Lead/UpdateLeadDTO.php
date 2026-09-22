<?php

namespace App\DTOs\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use Carbon\Carbon;

readonly class UpdateLeadDTO
{
    public function __construct(
        public ?int $contactId = null,
        public ?string $title = null,
        public ?int $assignedUserId = null,
        public ?LeadSource $leadSource = null,
        public ?LeadStatus $status = null,
        public ?LeadTemperature $temperature = null,
        public ?int $score = null,
        public ?float $budgetMin = null,
        public ?float $budgetMax = null,
        public ?string $budgetRange = null,
        public ?PurchaseTimeline $purchaseTimeline = null,
        public ?string $preferredLocation = null,
        public ?string $propertyInterest = null,
        public ?QualificationStatus $qualificationStatus = null,
        public ?string $notes = null,
        public ?string $lostReason = null,
        public ?Carbon $convertedAt = null,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $source = null;
        if (isset($data['lead_source'])) {
            $source = $data['lead_source'] instanceof LeadSource
                ? $data['lead_source']
                : LeadSource::tryFrom((string) $data['lead_source']);
        }

        $status = null;
        if (isset($data['status'])) {
            $status = $data['status'] instanceof LeadStatus
                ? $data['status']
                : LeadStatus::tryFrom((string) $data['status']);
        }

        $temperature = null;
        if (isset($data['temperature'])) {
            $temperature = $data['temperature'] instanceof LeadTemperature
                ? $data['temperature']
                : LeadTemperature::tryFrom((string) $data['temperature']);
        }

        $timeline = null;
        if (isset($data['purchase_timeline'])) {
            $timeline = $data['purchase_timeline'] instanceof PurchaseTimeline
                ? $data['purchase_timeline']
                : PurchaseTimeline::tryFrom((string) $data['purchase_timeline']);
        }

        $qualification = null;
        if (isset($data['qualification_status'])) {
            $qualification = $data['qualification_status'] instanceof QualificationStatus
                ? $data['qualification_status']
                : QualificationStatus::tryFrom((string) $data['qualification_status']);
        }

        $convertedAt = null;
        if (! empty($data['converted_at'])) {
            $convertedAt = Carbon::parse($data['converted_at']);
        } elseif ($status === LeadStatus::Won) {
            $convertedAt = now();
        }

        return new self(
            contactId: isset($data['contact_id']) ? (int) $data['contact_id'] : null,
            title: isset($data['title']) ? trim((string) $data['title']) : null,
            assignedUserId: array_key_exists('assigned_user_id', $data) ? (! empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null) : null,
            leadSource: $source,
            status: $status,
            temperature: $temperature,
            score: isset($data['score']) && $data['score'] !== '' ? (int) $data['score'] : null,
            budgetMin: isset($data['budget_min']) && $data['budget_min'] !== '' ? (float) $data['budget_min'] : null,
            budgetMax: isset($data['budget_max']) && $data['budget_max'] !== '' ? (float) $data['budget_max'] : null,
            budgetRange: isset($data['budget_range']) ? trim((string) $data['budget_range']) : null,
            purchaseTimeline: $timeline,
            preferredLocation: isset($data['preferred_location']) ? trim((string) $data['preferred_location']) : null,
            propertyInterest: isset($data['property_interest']) ? trim((string) $data['property_interest']) : null,
            qualificationStatus: $qualification,
            notes: isset($data['notes']) ? trim((string) $data['notes']) : null,
            lostReason: isset($data['lost_reason']) ? trim((string) $data['lost_reason']) : null,
            convertedAt: $convertedAt,
        );
    }

    /**
     * Transform to array for model persistence, omitting unset attributes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $fields = [
            'contact_id' => $this->contactId,
            'title' => $this->title,
            'assigned_user_id' => $this->assignedUserId,
            'lead_source' => $this->leadSource?->value,
            'status' => $this->status?->value,
            'temperature' => $this->temperature?->value,
            'score' => $this->score,
            'budget_min' => $this->budgetMin,
            'budget_max' => $this->budgetMax,
            'budget_range' => $this->budgetRange,
            'purchase_timeline' => $this->purchaseTimeline?->value,
            'preferred_location' => $this->preferredLocation,
            'property_interest' => $this->propertyInterest,
            'qualification_status' => $this->qualificationStatus?->value,
            'notes' => $this->notes,
            'lost_reason' => $this->lostReason,
            'converted_at' => $this->convertedAt?->toDateTimeString(),
        ];

        return array_filter($fields, fn ($value) => $value !== null);
    }
}
