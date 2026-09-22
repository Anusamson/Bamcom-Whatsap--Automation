<?php

namespace App\DTOs\Lead;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;

readonly class CreateLeadDTO
{
    public function __construct(
        public int $contactId,
        public string $title,
        public ?int $assignedUserId = null,
        public LeadSource $leadSource = LeadSource::WhatsApp,
        public LeadStatus $status = LeadStatus::New,
        public LeadTemperature $temperature = LeadTemperature::Warm,
        public ?int $score = null,
        public ?float $budgetMin = null,
        public ?float $budgetMax = null,
        public ?string $budgetRange = null,
        public PurchaseTimeline $purchaseTimeline = PurchaseTimeline::OneToThreeMonths,
        public ?string $preferredLocation = null,
        public ?string $propertyInterest = null,
        public QualificationStatus $qualificationStatus = QualificationStatus::Unqualified,
        public ?string $notes = null,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $source = LeadSource::WhatsApp;
        if (! empty($data['lead_source'])) {
            $source = $data['lead_source'] instanceof LeadSource
                ? $data['lead_source']
                : (LeadSource::tryFrom((string) $data['lead_source']) ?? LeadSource::WhatsApp);
        }

        $status = LeadStatus::New;
        if (! empty($data['status'])) {
            $status = $data['status'] instanceof LeadStatus
                ? $data['status']
                : (LeadStatus::tryFrom((string) $data['status']) ?? LeadStatus::New);
        }

        $temperature = LeadTemperature::Warm;
        if (! empty($data['temperature'])) {
            $temperature = $data['temperature'] instanceof LeadTemperature
                ? $data['temperature']
                : (LeadTemperature::tryFrom((string) $data['temperature']) ?? LeadTemperature::Warm);
        }

        $timeline = PurchaseTimeline::OneToThreeMonths;
        if (! empty($data['purchase_timeline'])) {
            $timeline = $data['purchase_timeline'] instanceof PurchaseTimeline
                ? $data['purchase_timeline']
                : (PurchaseTimeline::tryFrom((string) $data['purchase_timeline']) ?? PurchaseTimeline::OneToThreeMonths);
        }

        $qualification = QualificationStatus::Unqualified;
        if (! empty($data['qualification_status'])) {
            $qualification = $data['qualification_status'] instanceof QualificationStatus
                ? $data['qualification_status']
                : (QualificationStatus::tryFrom((string) $data['qualification_status']) ?? QualificationStatus::Unqualified);
        }

        return new self(
            contactId: (int) $data['contact_id'],
            title: trim((string) $data['title']),
            assignedUserId: ! empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            leadSource: $source,
            status: $status,
            temperature: $temperature,
            score: isset($data['score']) && $data['score'] !== '' ? (int) $data['score'] : null,
            budgetMin: isset($data['budget_min']) && $data['budget_min'] !== '' ? (float) $data['budget_min'] : null,
            budgetMax: isset($data['budget_max']) && $data['budget_max'] !== '' ? (float) $data['budget_max'] : null,
            budgetRange: ! empty($data['budget_range']) ? trim((string) $data['budget_range']) : null,
            purchaseTimeline: $timeline,
            preferredLocation: ! empty($data['preferred_location']) ? trim((string) $data['preferred_location']) : null,
            propertyInterest: ! empty($data['property_interest']) ? trim((string) $data['property_interest']) : null,
            qualificationStatus: $qualification,
            notes: ! empty($data['notes']) ? trim((string) $data['notes']) : null,
        );
    }

    /**
     * Transform to array for model persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contact_id' => $this->contactId,
            'title' => $this->title,
            'assigned_user_id' => $this->assignedUserId,
            'lead_source' => $this->leadSource->value,
            'status' => $this->status->value,
            'temperature' => $this->temperature->value,
            'score' => $this->score,
            'budget_min' => $this->budgetMin,
            'budget_max' => $this->budgetMax,
            'budget_range' => $this->budgetRange,
            'purchase_timeline' => $this->purchaseTimeline->value,
            'preferred_location' => $this->preferredLocation,
            'property_interest' => $this->propertyInterest,
            'qualification_status' => $this->qualificationStatus->value,
            'notes' => $this->notes,
        ];
    }
}
