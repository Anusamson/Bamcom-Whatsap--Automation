<?php

namespace App\Services\Lead;

use App\DTOs\Lead\CreateLeadDTO;
use App\DTOs\Lead\UpdateLeadDTO;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Services\BaseService;
use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadService extends BaseService
{
    public function __construct(
        protected ?LeadScoringService $scoringService = null
    ) {
        if (! $this->scoringService && function_exists('app') && class_exists(Container::class) && Container::getInstance()) {
            $this->scoringService = app(LeadScoringService::class);
        }
    }

    /**
     * Get paginated sales leads with multi-faceted filtering.
     *
     * @param  array{status?: ?string, temperature?: ?string, agent?: ?mixed, source?: ?string, property?: ?string, date?: ?string, date_from?: ?string, date_to?: ?string, contact_id?: ?int, search?: ?string, per_page?: ?int}  $filters
     */
    public function getPaginatedLeads(array $filters = []): LengthAwarePaginator
    {
        $query = Lead::query()
            ->with(['contact', 'assignedUser.profile', 'assignedUser.team'])
            ->latest('id');

        // Search text filter
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Temperature filter (Hot, Warm, Cold)
        if (! empty($filters['temperature'])) {
            $query->temperature($filters['temperature']);
        }

        // Agent / Representative filter
        $agentFilter = $filters['agent'] ?? $filters['assigned_user_id'] ?? null;
        if ($agentFilter !== null && $agentFilter !== '') {
            if ($agentFilter === 'unassigned') {
                $query->unassigned();
            } else {
                $query->assignedTo((int) $agentFilter);
            }
        }

        // Source filter
        $sourceFilter = $filters['source'] ?? $filters['lead_source'] ?? null;
        if (! empty($sourceFilter)) {
            $query->source($sourceFilter);
        }

        // Property interest filter
        $propertyFilter = $filters['property'] ?? $filters['property_interest'] ?? null;
        if (! empty($propertyFilter)) {
            $query->property((string) $propertyFilter);
        }

        // Date range or date preset filter
        $datePreset = $filters['date'] ?? null;
        $dateFrom = $filters['date_from'] ?? $filters['from_date'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to_date'] ?? null;
        if ($datePreset || $dateFrom || $dateTo) {
            $query->dateRange($dateFrom, $dateTo, $datePreset);
        }

        // Direct contact filter
        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }

        $perPage = ! empty($filters['per_page']) ? (int) $filters['per_page'] : 15;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new sales opportunity for a contact.
     */
    public function createLead(CreateLeadDTO $dto): Lead
    {
        return $this->transaction(function () use ($dto): Lead {
            $attributes = $dto->toArray();

            // Calculate initial score if not explicitly set
            if ($attributes['score'] === null) {
                $tempLead = new Lead($attributes);
                $attributes['score'] = $this->calculateScore($tempLead);
            }

            /** @var Lead $lead */
            $lead = Lead::create($attributes);

            // Update contact's last contact timestamp
            Contact::where('id', $lead->contact_id)->update(['last_contact_at' => now()]);

            // Evaluate configurable scoring rules for initial enquiry and profile
            $this->scoringService?->recordEvent($lead, 'new_enquiry');
            $this->scoringService?->evaluateProfileEvents($lead);

            $this->logInfo('Lead opportunity created', [
                'lead_id' => $lead->id,
                'uuid' => $lead->uuid,
                'contact_id' => $lead->contact_id,
            ]);

            return $lead->fresh(['contact', 'assignedUser.profile']);
        });
    }

    /**
     * Update an existing lead opportunity.
     */
    public function updateLead(Lead $lead, UpdateLeadDTO $dto): Lead
    {
        return $this->transaction(function () use ($lead, $dto): Lead {
            $attributes = $dto->toArray();

            // Recalculate score if not provided
            if (! array_key_exists('score', $attributes)) {
                $tempLead = (clone $lead)->fill($attributes);
                $attributes['score'] = $this->calculateScore($tempLead);
            }

            $lead->update($attributes);

            // Check if updated attributes satisfy scoring rules
            $this->scoringService?->evaluateProfileEvents($lead);

            $this->logInfo('Lead opportunity updated', [
                'lead_id' => $lead->id,
                'uuid' => $lead->uuid,
            ]);

            return $lead->fresh(['contact', 'assignedUser.profile']);
        });
    }

    /**
     * Soft delete a lead opportunity.
     */
    public function deleteLead(Lead $lead): bool
    {
        return $this->transaction(function () use ($lead): bool {
            $leadId = $lead->id;
            $deleted = (bool) $lead->delete();

            $this->logInfo('Lead opportunity archived', ['lead_id' => $leadId]);

            return $deleted;
        });
    }

    /**
     * Update sales pipeline stage / status.
     */
    public function updateStatus(Lead $lead, LeadStatus $status, ?string $lostReason = null): Lead
    {
        return $this->transaction(function () use ($lead, $status, $lostReason): Lead {
            $updates = ['status' => $status];

            if ($status === LeadStatus::Won && $lead->converted_at === null) {
                $updates['converted_at'] = now();
            }

            if (in_array($status, [LeadStatus::Lost, LeadStatus::Disqualified], true) && $lostReason !== null) {
                $updates['lost_reason'] = $lostReason;
            }

            $lead->update($updates);

            $this->logInfo('Lead stage updated', [
                'lead_id' => $lead->id,
                'status' => $status->value,
            ]);

            return $lead->fresh(['contact', 'assignedUser']);
        });
    }

    /**
     * Assign sales representative to the lead.
     */
    public function assignRepresentative(Lead $lead, ?int $userId): Lead
    {
        return $this->transaction(function () use ($lead, $userId): Lead {
            $lead->update(['assigned_user_id' => $userId]);

            $this->logInfo('Lead assigned to representative', [
                'lead_id' => $lead->id,
                'assigned_user_id' => $userId,
            ]);

            return $lead->fresh(['contact', 'assignedUser']);
        });
    }

    /**
     * Compute readiness lead score (0 - 100) based on signals.
     */
    public function calculateScore(Lead $lead): int
    {
        $score = 20; // baseline

        // Temperature weight
        $score += match ($lead->temperature) {
            LeadTemperature::Hot => 35,
            LeadTemperature::Warm => 20,
            LeadTemperature::Cold => 5,
        };

        // Purchase timeline weight
        $score += match ($lead->purchase_timeline) {
            PurchaseTimeline::Immediate => 25,
            PurchaseTimeline::OneToThreeMonths => 15,
            PurchaseTimeline::ThreeToSixMonths => 10,
            PurchaseTimeline::SixToTwelveMonths => 5,
            PurchaseTimeline::Flexible => 0,
        };

        // Qualification weight
        $score += match ($lead->qualification_status) {
            QualificationStatus::Qualified => 20,
            QualificationStatus::InReview => 10,
            QualificationStatus::Unqualified => 0,
            QualificationStatus::Disqualified => -20,
        };

        // Budget specified bonus
        if ($lead->budget_max > 0 || $lead->budget_min > 0 || ! empty($lead->budget_range)) {
            $score += 10;
        }

        return (int) max(0, min(100, $score));
    }

    /**
     * Pipeline summary metrics for dashboard and listing view.
     *
     * @return array{total: int, hot: int, warm: int, cold: int, qualified: int, negotiation: int, won: int, total_pipeline_value: float}
     */
    public function getLeadMetrics(): array
    {
        return [
            'total' => Lead::count(),
            'hot' => Lead::where('temperature', LeadTemperature::Hot->value)->count(),
            'warm' => Lead::where('temperature', LeadTemperature::Warm->value)->count(),
            'cold' => Lead::where('temperature', LeadTemperature::Cold->value)->count(),
            'qualified' => Lead::where('status', LeadStatus::Qualified->value)->count(),
            'negotiation' => Lead::where('status', LeadStatus::Negotiation->value)->count(),
            'won' => Lead::where('status', LeadStatus::Won->value)->count(),
            'total_pipeline_value' => (float) Lead::whereNotIn('status', [LeadStatus::Lost->value, LeadStatus::Disqualified->value])
                ->sum('budget_max'),
        ];
    }
}
