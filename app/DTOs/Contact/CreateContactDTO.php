<?php

namespace App\DTOs\Contact;

use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use Carbon\Carbon;

readonly class CreateContactDTO
{
    public function __construct(
        public string $firstName,
        public ?string $lastName,
        public string $phone,
        public ?string $email = null,
        public ?string $location = null,
        public ?string $occupation = null,
        public string $preferredLanguage = 'en',
        public LeadSource $leadSource = LeadSource::WhatsApp,
        public ?int $assignedUserId = null,
        public ContactStatus $status = ContactStatus::Lead,
        public ?Carbon $lastContactAt = null,
    ) {}

    /**
     * Build instance from validated array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = ContactStatus::Lead;
        if (! empty($data['status'])) {
            $status = $data['status'] instanceof ContactStatus
                ? $data['status']
                : (ContactStatus::tryFrom((string) $data['status']) ?? ContactStatus::Lead);
        }

        $source = LeadSource::WhatsApp;
        if (! empty($data['lead_source'])) {
            $source = $data['lead_source'] instanceof LeadSource
                ? $data['lead_source']
                : (LeadSource::tryFrom((string) $data['lead_source']) ?? LeadSource::WhatsApp);
        }

        $lastContactAt = null;
        if (! empty($data['last_contact_at'])) {
            $lastContactAt = Carbon::parse($data['last_contact_at']);
        }

        return new self(
            firstName: trim((string) $data['first_name']),
            lastName: ! empty($data['last_name']) ? trim((string) $data['last_name']) : null,
            phone: trim((string) $data['phone']),
            email: ! empty($data['email']) ? trim((string) $data['email']) : null,
            location: ! empty($data['location']) ? trim((string) $data['location']) : null,
            occupation: ! empty($data['occupation']) ? trim((string) $data['occupation']) : null,
            preferredLanguage: ! empty($data['preferred_language']) ? trim((string) $data['preferred_language']) : 'en',
            leadSource: $source,
            assignedUserId: ! empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            status: $status,
            lastContactAt: $lastContactAt,
        );
    }

    /**
     * Transform to array for Eloquent model attributes.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'phone' => $this->phone,
            'email' => $this->email,
            'location' => $this->location,
            'occupation' => $this->occupation,
            'preferred_language' => $this->preferredLanguage,
            'lead_source' => $this->leadSource->value,
            'assigned_user_id' => $this->assignedUserId,
            'status' => $this->status->value,
            'last_contact_at' => $this->lastContactAt,
        ];
    }
}
