<?php

namespace App\Services\Contact;

use App\DTOs\Contact\CreateContactDTO;
use App\DTOs\Contact\UpdateContactDTO;
use App\Enums\ContactStatus;
use App\Enums\LeadSource;
use App\Models\Contact;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ContactService extends BaseService
{
    public function __construct(
        protected PhoneNormalizerService $phoneNormalizer
    ) {}

    /**
     * Get paginated contacts with comprehensive filters and search.
     *
     * @param  array{search?: ?string, status?: ?string, lead_source?: ?string, assigned_user_id?: ?mixed, per_page?: ?int}  $filters
     */
    public function getPaginatedContacts(array $filters = []): LengthAwarePaginator
    {
        $query = Contact::query()
            ->with(['assignedUser.profile'])
            ->latest('id');

        // Search across names, phone, email, location, occupation
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // Filter by contact lifecycle status
        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Filter by acquisition lead source
        if (! empty($filters['lead_source'])) {
            $query->leadSource($filters['lead_source']);
        }

        // Filter by assigned user / rep
        if (isset($filters['assigned_user_id']) && $filters['assigned_user_id'] !== '') {
            if ($filters['assigned_user_id'] === 'unassigned') {
                $query->unassigned();
            } else {
                $query->assignedTo((int) $filters['assigned_user_id']);
            }
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new contact with phone normalization and duplicate prevention.
     *
     * @throws ValidationException
     */
    public function createContact(CreateContactDTO $dto): Contact
    {
        $normalizedPhone = $this->phoneNormalizer->normalize($dto->phone);

        // Check for duplicate normalized phone number
        $existing = Contact::where('phone', $normalizedPhone)->first();
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'phone' => 'A contact with this phone number already exists: '.$existing->full_name.' ('.$normalizedPhone.').',
            ]);
        }

        return $this->transaction(function () use ($dto, $normalizedPhone): Contact {
            $attributes = $dto->toArray();
            $attributes['phone'] = $normalizedPhone;

            /** @var Contact $contact */
            $contact = Contact::create($attributes);

            $this->logInfo('Contact registered successfully', [
                'contact_id' => $contact->id,
                'uuid' => $contact->uuid,
                'phone' => $contact->phone,
            ]);

            return $contact->fresh(['assignedUser.profile']);
        });
    }

    /**
     * Update an existing contact with phone normalization and duplicate conflict prevention.
     *
     * @throws ValidationException
     */
    public function updateContact(Contact $contact, UpdateContactDTO $dto): Contact
    {
        $normalizedPhone = $this->phoneNormalizer->normalize($dto->phone);

        // Verify that the updated phone does not conflict with another existing contact
        $duplicate = Contact::where('phone', $normalizedPhone)
            ->where('id', '!=', $contact->id)
            ->first();

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'phone' => 'The normalized phone number '.$normalizedPhone.' is already assigned to '.$duplicate->full_name.'.',
            ]);
        }

        return $this->transaction(function () use ($contact, $dto, $normalizedPhone): Contact {
            $attributes = $dto->toArray();
            $attributes['phone'] = $normalizedPhone;

            $contact->update($attributes);

            $this->logInfo('Contact updated successfully', [
                'contact_id' => $contact->id,
                'uuid' => $contact->uuid,
            ]);

            return $contact->fresh(['assignedUser.profile']);
        });
    }

    /**
     * Safe soft deletion of a contact.
     */
    public function deleteContact(Contact $contact): bool
    {
        return $this->transaction(function () use ($contact): bool {
            $contactId = $contact->id;
            $deleted = (bool) $contact->delete();

            $this->logInfo('Contact archived/deleted', ['contact_id' => $contactId]);

            return $deleted;
        });
    }

    /**
     * Quick status update for a contact.
     */
    public function updateStatus(Contact $contact, ContactStatus $status): Contact
    {
        return $this->transaction(function () use ($contact, $status): Contact {
            $contact->update(['status' => $status]);

            $this->logInfo('Contact status changed', [
                'contact_id' => $contact->id,
                'status' => $status->value,
            ]);

            return $contact->fresh(['assignedUser']);
        });
    }

    /**
     * Assign contact to a sales agent or team member.
     */
    public function assignUser(Contact $contact, ?int $userId): Contact
    {
        return $this->transaction(function () use ($contact, $userId): Contact {
            $contact->update(['assigned_user_id' => $userId]);

            $this->logInfo('Contact reassigned', [
                'contact_id' => $contact->id,
                'assigned_user_id' => $userId,
            ]);

            return $contact->fresh(['assignedUser']);
        });
    }

    /**
     * Log a direct interaction touchpoint with the contact.
     */
    public function recordTouchpoint(Contact $contact, ?Carbon $timestamp = null): Contact
    {
        $contact->update([
            'last_contact_at' => $timestamp ?? now(),
        ]);

        return $contact->fresh(['assignedUser']);
    }

    /**
     * Compute summary metrics for contacts dashboard widget.
     *
     * @return array{total: int, leads: int, prospects: int, customers: int, unassigned: int, whatsapp_leads: int}
     */
    public function getContactMetrics(): array
    {
        return [
            'total' => Contact::count(),
            'leads' => Contact::where('status', ContactStatus::Lead->value)->count(),
            'prospects' => Contact::where('status', ContactStatus::Prospect->value)->count(),
            'customers' => Contact::where('status', ContactStatus::Customer->value)->count(),
            'unassigned' => Contact::unassigned()->count(),
            'whatsapp_leads' => Contact::where('lead_source', LeadSource::WhatsApp->value)->count(),
        ];
    }
}
