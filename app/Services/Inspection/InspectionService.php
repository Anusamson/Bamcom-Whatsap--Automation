<?php

namespace App\Services\Inspection;

use App\Enums\InspectionStatus;
use App\Enums\LeadTemperature;
use App\Events\InspectionCancelled;
use App\Events\InspectionCompleted;
use App\Events\InspectionConfirmed;
use App\Events\InspectionNoShow;
use App\Events\InspectionRescheduled;
use App\Events\InspectionScheduled;
use App\Events\InspectionStatusChanged;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Inspection;
use App\Models\Lead;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Lead\LeadScoringService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionService extends BaseService
{
    public function __construct(
        protected ?LeadScoringService $scoringService = null
    ) {
        if (! $this->scoringService && function_exists('app') && class_exists(Container::class) && Container::getInstance()) {
            $this->scoringService = app(LeadScoringService::class);
        }
    }

    /**
     * Get paginated inspections with multi-criteria filtering.
     *
     * @param  array{status?: ?string, representative_id?: ?int, property_id?: ?int, contact_id?: ?int, search?: ?string, date?: ?string, date_from?: ?string, date_to?: ?string, per_page?: ?int}  $filters
     */
    public function getPaginatedInspections(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Inspection::query()
            ->with(['contact', 'property.estate', 'representative.profile', 'creator'])
            ->latest('inspection_date')
            ->latest('id');

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Representative filter
        if (! empty($filters['representative_id'])) {
            $query->where('representative_id', (int) $filters['representative_id']);
        }

        // Property filter
        if (! empty($filters['property_id'])) {
            $query->where('property_id', (int) $filters['property_id']);
        }

        // Contact filter
        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }

        // Specific date filter
        if (! empty($filters['date'])) {
            $query->whereDate('inspection_date', $filters['date']);
        }

        // Date range filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('inspection_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('inspection_date', '<=', $filters['date_to']);
        }

        // Search filter across contact, property, notes
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function (Builder $q) use ($term): void {
                $q->where('estate_name', 'like', "%{$term}%")
                    ->orWhere('meeting_point', 'like', "%{$term}%")
                    ->orWhere('customer_notes', 'like', "%{$term}%")
                    ->orWhere('sales_notes', 'like', "%{$term}%")
                    ->orWhereHas('contact', function (Builder $cq) use ($term): void {
                        $cq->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%");
                    })
                    ->orWhereHas('property', function (Builder $pq) use ($term): void {
                        $pq->where('title', 'like', "%{$term}%")
                            ->orWhere('location', 'like', "%{$term}%");
                    });
            });
        }

        return $query->paginate($filters['per_page'] ?? $perPage)->withQueryString();
    }

    /**
     * Get inspection events formatted for calendar display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarEvents(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $representativeId = null,
        ?string $status = null
    ): array {
        $start = $startDate ?: now()->startOfMonth()->subDays(7)->toDateString();
        $end = $endDate ?: now()->endOfMonth()->addDays(7)->toDateString();

        $query = Inspection::query()
            ->with(['contact', 'property', 'representative'])
            ->whereBetween('inspection_date', [$start, $end]);

        if ($representativeId) {
            $query->where('representative_id', $representativeId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $inspections = $query->orderBy('inspection_date')->orderBy('inspection_time')->get();

        return $inspections->map(function (Inspection $inspection): array {
            $contactName = $inspection->contact ? $inspection->contact->full_name : 'Client';
            $repName = $inspection->representative ? $inspection->representative->name : 'Unassigned';

            return [
                'id' => $inspection->id,
                'uuid' => $inspection->uuid,
                'title' => "{$contactName} - {$inspection->target_title}",
                'date' => $inspection->inspection_date->format('Y-m-d'),
                'time' => $inspection->inspection_time,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'status_color' => $inspection->status->color(),
                'contact' => [
                    'id' => $inspection->contact_id,
                    'name' => $contactName,
                    'phone' => $inspection->contact?->phone,
                ],
                'property' => $inspection->property ? [
                    'id' => $inspection->property->id,
                    'title' => $inspection->property->title,
                    'location' => $inspection->property->location,
                ] : null,
                'estate_name' => $inspection->estate_name,
                'representative' => $inspection->representative ? [
                    'id' => $inspection->representative->id,
                    'name' => $repName,
                    'email' => $inspection->representative->email,
                ] : null,
                'meeting_point' => $inspection->meeting_point,
                'customer_notes' => $inspection->customer_notes,
                'outcome' => $inspection->outcome,
            ];
        })->toArray();
    }

    /**
     * Check if a representative already has a conflicting inspection at the given date and time.
     */
    public function hasConflict(
        ?int $representativeId,
        string $date,
        string $time,
        ?int $excludeInspectionId = null
    ): ?Inspection {
        if (! $representativeId) {
            return null;
        }

        $formattedDate = Carbon::parse($date)->toDateString();

        return Inspection::query()
            ->where('representative_id', $representativeId)
            ->whereDate('inspection_date', $formattedDate)
            ->where('inspection_time', $time)
            ->when($excludeInspectionId, fn (Builder $q) => $q->where('id', '!=', $excludeInspectionId))
            ->whereIn('status', [
                InspectionStatus::Requested->value,
                InspectionStatus::Scheduled->value,
                InspectionStatus::Confirmed->value,
                InspectionStatus::Rescheduled->value,
            ])
            ->first();
    }

    /**
     * Schedule a new site inspection.
     *
     * @param  array{contact_id: int, lead_id?: ?int, property_id?: ?int, estate_name?: ?string, representative_id?: ?int, status?: ?string, inspection_date: string, inspection_time?: ?string, meeting_point?: ?string, customer_notes?: ?string, sales_notes?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function scheduleInspection(array $data, ?User $creator = null): Inspection
    {
        $repId = ! empty($data['representative_id']) ? (int) $data['representative_id'] : null;
        $date = Carbon::parse($data['inspection_date'])->toDateString();
        $time = ! empty($data['inspection_time']) ? trim($data['inspection_time']) : '10:00 AM';

        // Conflict check
        if ($repId) {
            $conflicting = $this->hasConflict($repId, $date, $time);
            if ($conflicting) {
                $rep = User::find($repId);
                $repName = $rep ? $rep->name : "Representative #{$repId}";
                throw ValidationException::withMessages([
                    'representative_id' => ["{$repName} is already assigned to an inspection on {$date} at {$time} ({$conflicting->target_title})."],
                ]);
            }
        }

        return DB::transaction(function () use ($data, $repId, $date, $time, $creator): Inspection {
            // Find or associate lead if not supplied
            $contactId = (int) $data['contact_id'];
            $leadId = ! empty($data['lead_id']) ? (int) $data['lead_id'] : null;

            if (! $leadId) {
                $contact = Contact::find($contactId);
                $lead = $contact?->leads()->latest()->first();
                $leadId = $lead?->id;
            } else {
                $lead = Lead::find($leadId);
            }

            $status = ! empty($data['status'])
                ? ($data['status'] instanceof InspectionStatus ? $data['status'] : InspectionStatus::from($data['status']))
                : InspectionStatus::Scheduled;

            /** @var Inspection $inspection */
            $inspection = Inspection::create([
                'contact_id' => $contactId,
                'lead_id' => $leadId,
                'property_id' => ! empty($data['property_id']) ? (int) $data['property_id'] : null,
                'estate_name' => $data['estate_name'] ?? null,
                'representative_id' => $repId,
                'status' => $status,
                'inspection_date' => $date,
                'inspection_time' => $time,
                'meeting_point' => ! empty($data['meeting_point']) ? $data['meeting_point'] : 'Bamcom Corporate Office, Plot 12, Admiralty Way, Lekki Phase 1, Lagos',
                'customer_notes' => $data['customer_notes'] ?? null,
                'sales_notes' => $data['sales_notes'] ?? null,
                'created_by_id' => $creator?->id,
            ]);

            // Create CRM Timeline Activity
            Activity::create([
                'lead_id' => $leadId,
                'user_id' => $creator?->id ?? $repId,
                'activity_type' => 'inspection_scheduled',
                'description' => "Site inspection scheduled for {$inspection->formatted_date_time} at {$inspection->target_title}.",
                'properties' => [
                    'inspection_id' => $inspection->id,
                    'uuid' => $inspection->uuid,
                    'contact_id' => $contactId,
                    'property_id' => $inspection->property_id,
                    'estate_name' => $inspection->estate_name,
                    'date' => $date,
                    'time' => $time,
                    'meeting_point' => $inspection->meeting_point,
                    'representative_id' => $repId,
                ],
            ]);

            // Dispatch domain event
            InspectionScheduled::dispatch($inspection, $creator, ['source' => 'system']);
            InspectionStatusChanged::dispatch($inspection, $status, $status, $creator);

            // Award lead scoring points for inspection request (+20) & ensure lead upgraded
            if ($lead) {
                $this->scoringService?->recordEvent(
                    $lead,
                    'inspection_request',
                    [
                        'inspection_id' => $inspection->id,
                        'date' => $date,
                        'time' => $time,
                        'property' => $inspection->target_title,
                    ],
                    $creator,
                    'inspection_service'
                );

                $lead->refresh();
                if ($lead->temperature !== LeadTemperature::Hot) {
                    $lead->update([
                        'temperature' => LeadTemperature::Hot,
                        'score' => max(60, (int) $lead->score),
                    ]);
                }
            }

            return $inspection->fresh(['contact', 'property', 'representative', 'creator']);
        });
    }

    /**
     * Update the status of an inspection and optionally record outcome / notes.
     *
     * @param  array{outcome?: ?string, sales_notes?: ?string, meeting_point?: ?string}  $attributes
     *
     * @throws ValidationException
     */
    public function updateStatus(
        Inspection $inspection,
        InspectionStatus|string $status,
        array $attributes = [],
        ?User $actor = null
    ): Inspection {
        $targetStatus = $status instanceof InspectionStatus ? $status : InspectionStatus::from($status);
        $oldStatus = $inspection->status;

        // If transitioning from a non-conflicting state (cancelled/no-show) back into an active state, check conflict
        if (! $oldStatus->canConflict() && $targetStatus->canConflict() && $inspection->representative_id) {
            $conflict = $this->hasConflict(
                $inspection->representative_id,
                $inspection->inspection_date->toDateString(),
                $inspection->inspection_time,
                $inspection->id
            );

            if ($conflict) {
                $repName = $inspection->representative ? $inspection->representative->name : 'Assigned representative';
                throw ValidationException::withMessages([
                    'status' => ["Cannot reactivate inspection: {$repName} is already assigned to another inspection on this date/time."],
                ]);
            }
        }

        return DB::transaction(function () use ($inspection, $oldStatus, $targetStatus, $attributes, $actor): Inspection {
            $updateData = ['status' => $targetStatus];

            if (array_key_exists('outcome', $attributes)) {
                $updateData['outcome'] = $attributes['outcome'];
            }
            if (array_key_exists('sales_notes', $attributes)) {
                $updateData['sales_notes'] = $attributes['sales_notes'];
            }
            if (array_key_exists('meeting_point', $attributes)) {
                $updateData['meeting_point'] = $attributes['meeting_point'];
            }

            $inspection->update($updateData);

            // Record CRM timeline activity
            Activity::create([
                'lead_id' => $inspection->lead_id,
                'user_id' => $actor?->id,
                'activity_type' => "inspection_{$targetStatus->value}",
                'description' => "Site inspection status changed from {$oldStatus->label()} to {$targetStatus->label()}.".($inspection->outcome ? " Outcome: {$inspection->outcome}" : ''),
                'properties' => [
                    'inspection_id' => $inspection->id,
                    'old_status' => $oldStatus->value,
                    'new_status' => $targetStatus->value,
                    'outcome' => $inspection->outcome,
                    'updated_by' => $actor?->name,
                ],
            ]);

            // Dispatch specific domain events
            match ($targetStatus) {
                InspectionStatus::Confirmed => InspectionConfirmed::dispatch($inspection, $actor, $attributes),
                InspectionStatus::Completed => InspectionCompleted::dispatch($inspection, $inspection->outcome, $actor, $attributes),
                InspectionStatus::Cancelled => InspectionCancelled::dispatch($inspection, $inspection->outcome, $actor, $attributes),
                InspectionStatus::NoShow => InspectionNoShow::dispatch($inspection, $actor, $attributes),
                default => null,
            };

            InspectionStatusChanged::dispatch($inspection, $oldStatus, $targetStatus, $actor, $attributes);

            // If marked as Completed, trigger lead scoring rule inspection_completed (+20)
            if ($targetStatus === InspectionStatus::Completed && $inspection->lead) {
                $this->scoringService?->recordEvent(
                    $inspection->lead,
                    'inspection_completed',
                    [
                        'inspection_id' => $inspection->id,
                        'outcome' => $inspection->outcome,
                        'property' => $inspection->target_title,
                    ],
                    $actor,
                    'inspection_service'
                );
            }

            return $inspection->fresh(['contact', 'property', 'representative']);
        });
    }

    /**
     * Reschedule an inspection to a new date and time with conflict prevention.
     *
     * @throws ValidationException
     */
    public function reschedule(
        Inspection $inspection,
        string $newDate,
        string $newTime,
        ?string $reason = null,
        ?int $representativeId = null,
        ?User $actor = null
    ): Inspection {
        $repId = $representativeId ?? $inspection->representative_id;
        $formattedDate = Carbon::parse($newDate)->toDateString();
        $formattedTime = trim($newTime);

        // Check for conflicting assignment on new slot
        if ($repId) {
            $conflict = $this->hasConflict($repId, $formattedDate, $formattedTime, $inspection->id);
            if ($conflict) {
                $rep = User::find($repId);
                $repName = $rep ? $rep->name : "Representative #{$repId}";
                throw ValidationException::withMessages([
                    'new_time' => ["{$repName} is already assigned to an inspection on {$formattedDate} at {$formattedTime}."],
                ]);
            }
        }

        $oldDate = $inspection->inspection_date->format('Y-m-d');
        $oldTime = $inspection->inspection_time;

        return DB::transaction(function () use ($inspection, $oldDate, $oldTime, $formattedDate, $formattedTime, $repId, $reason, $actor): Inspection {
            $notes = $inspection->sales_notes;
            if ($reason) {
                $notes = trim(($notes ? $notes."\n" : '')."[Rescheduled from {$oldDate} {$oldTime}]: {$reason}");
            }

            $inspection->update([
                'inspection_date' => $formattedDate,
                'inspection_time' => $formattedTime,
                'representative_id' => $repId,
                'status' => InspectionStatus::Rescheduled,
                'sales_notes' => $notes,
            ]);

            Activity::create([
                'lead_id' => $inspection->lead_id,
                'user_id' => $actor?->id,
                'activity_type' => 'inspection_rescheduled',
                'description' => "Inspection rescheduled from {$oldDate} {$oldTime} to {$formattedDate} at {$formattedTime}.".($reason ? " Reason: {$reason}" : ''),
                'properties' => [
                    'inspection_id' => $inspection->id,
                    'old_date' => $oldDate,
                    'old_time' => $oldTime,
                    'new_date' => $formattedDate,
                    'new_time' => $formattedTime,
                    'reason' => $reason,
                ],
            ]);

            InspectionRescheduled::dispatch($inspection, $oldDate, $oldTime, $formattedDate, $formattedTime, $actor);
            InspectionStatusChanged::dispatch($inspection, InspectionStatus::Scheduled, InspectionStatus::Rescheduled, $actor);

            return $inspection->fresh(['contact', 'property', 'representative']);
        });
    }

    /**
     * Assign or reassign a field sales representative to an inspection.
     *
     * @throws ValidationException
     */
    public function assignRepresentative(
        Inspection $inspection,
        User $representative,
        ?User $actor = null
    ): Inspection {
        // Only validate conflict if inspection is currently in an active status
        if ($inspection->status->canConflict()) {
            $conflict = $this->hasConflict(
                $representative->id,
                $inspection->inspection_date->toDateString(),
                $inspection->inspection_time,
                $inspection->id
            );

            if ($conflict) {
                throw ValidationException::withMessages([
                    'representative_id' => ["{$representative->name} is already assigned to another inspection on {$inspection->inspection_date->format('Y-m-d')} at {$inspection->inspection_time}."],
                ]);
            }
        }

        $inspection->update(['representative_id' => $representative->id]);

        Activity::create([
            'lead_id' => $inspection->lead_id,
            'user_id' => $actor?->id,
            'activity_type' => 'inspection_reassigned',
            'description' => "Representative {$representative->name} assigned to inspection for {$inspection->formatted_date_time}.",
            'properties' => [
                'inspection_id' => $inspection->id,
                'representative_id' => $representative->id,
                'representative_name' => $representative->name,
            ],
        ]);

        return $inspection->fresh(['contact', 'property', 'representative']);
    }
}
