<?php

namespace App\Services\Campaign;

use App\Enums\LeadTemperature;
use App\Models\Audience;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * High-performance query builder and segmentation engine for campaign audiences.
 * Supports segmentation by:
 * 1. lead stage
 * 2. temperature
 * 3. tag
 * 4. location
 * 5. property
 * 6. budget
 * 7. agent
 * 8. source
 * 9. inspection status
 * 10. last contact
 */
class AudienceSegmentationService
{
    /**
     * Build an Eloquent builder for Contact based on segmentation criteria.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Contact>
     */
    public function buildQuery(array $filters = []): Builder
    {
        $query = Contact::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        // 0. Safety Guardrail: Exclude opted-out contacts by default
        $excludeOptedOut = $filters['exclude_opted_out'] ?? true;
        if ($excludeOptedOut) {
            $query->where('has_opted_out', false);
        }

        // 1. Lead Stage
        $stageIds = array_filter((array) ($filters['stage_ids'] ?? $filters['stage_id'] ?? $filters['lead_stage'] ?? []));
        if (! empty($stageIds)) {
            $query->whereHas('leads', function (Builder $q) use ($stageIds): void {
                $q->whereIn('pipeline_stage_id', $stageIds);
            });
        }

        // 2. Temperature (cold, warm, hot)
        $temperatures = array_filter((array) ($filters['temperatures'] ?? $filters['temperature'] ?? []));
        if (! empty($temperatures)) {
            $temperatureValues = array_map(
                fn ($t) => $t instanceof LeadTemperature ? $t->value : (string) $t,
                $temperatures
            );

            $query->whereHas('leads', function (Builder $q) use ($temperatureValues): void {
                $q->whereIn('temperature', $temperatureValues);
            });
        }

        // 3. Tag (by name or slug)
        $tags = array_filter((array) ($filters['tags'] ?? $filters['tag'] ?? []));
        if (! empty($tags)) {
            $query->whereHas('tags', function (Builder $q) use ($tags): void {
                $q->whereIn('name', $tags)
                    ->orWhereIn('slug', $tags);
            });
        }

        // 4. Location (fuzzy substring match on contact location)
        $locations = array_filter((array) ($filters['locations'] ?? $filters['location'] ?? []));
        if (! empty($locations)) {
            $query->where(function (Builder $q) use ($locations): void {
                foreach ($locations as $loc) {
                    $q->orWhere('location', 'like', '%'.trim((string) $loc).'%');
                }
            });
        }

        // 5. Property (associated with leads or inspections)
        $propertyIds = array_filter((array) ($filters['property_ids'] ?? $filters['property_id'] ?? $filters['property'] ?? []));
        if (! empty($propertyIds)) {
            $query->where(function (Builder $q) use ($propertyIds): void {
                $q->whereHas('leads', function (Builder $lq) use ($propertyIds): void {
                    $lq->whereIn('property_id', $propertyIds);
                })->orWhereHas('inspections', function (Builder $iq) use ($propertyIds): void {
                    $iq->whereIn('property_id', $propertyIds);
                });
            });
        }

        // 6. Budget (deals value range)
        $minBudget = isset($filters['min_budget']) && is_numeric($filters['min_budget']) ? (float) $filters['min_budget'] : null;
        $maxBudget = isset($filters['max_budget']) && is_numeric($filters['max_budget']) ? (float) $filters['max_budget'] : null;

        if (! is_null($minBudget) || ! is_null($maxBudget)) {
            $query->whereHas('deals', function (Builder $dq) use ($minBudget, $maxBudget): void {
                if (! is_null($minBudget)) {
                    $dq->where('deal_value', '>=', $minBudget);
                }
                if (! is_null($maxBudget)) {
                    $dq->where('deal_value', '<=', $maxBudget);
                }
            });
        }

        // 7. Assigned Agent
        $agentIds = array_filter((array) ($filters['agent_ids'] ?? $filters['agent_id'] ?? $filters['assigned_user_id'] ?? []));
        if (! empty($agentIds)) {
            $query->whereIn('assigned_user_id', $agentIds);
        }

        // 8. Lead Source
        $sources = array_filter((array) ($filters['sources'] ?? $filters['source'] ?? $filters['lead_source'] ?? []));
        if (! empty($sources)) {
            $query->whereIn('lead_source', $sources);
        }

        // 9. Inspection Status (completed, scheduled, cancelled, or none/never)
        $inspectionStatuses = array_filter((array) ($filters['inspection_statuses'] ?? $filters['inspection_status'] ?? []));
        if (! empty($inspectionStatuses)) {
            $hasNone = in_array('none', $inspectionStatuses, true) || in_array('never', $inspectionStatuses, true);
            $realStatuses = array_diff($inspectionStatuses, ['none', 'never']);

            $query->where(function (Builder $q) use ($realStatuses, $hasNone): void {
                if (! empty($realStatuses)) {
                    $q->whereHas('inspections', function (Builder $iq) use ($realStatuses): void {
                        $iq->whereIn('status', $realStatuses);
                    });
                }

                if ($hasNone) {
                    if (! empty($realStatuses)) {
                        $q->orWhereDoesntHave('inspections');
                    } else {
                        $q->whereDoesntHave('inspections');
                    }
                }
            });
        }

        // 10. Last Contact Timing
        if (! empty($filters['last_contact_within_days'])) {
            $days = (int) $filters['last_contact_within_days'];
            $query->where('last_contact_at', '>=', now()->subDays($days));
        }

        if (! empty($filters['last_contact_before_days'])) {
            $days = (int) $filters['last_contact_before_days'];
            $cutoff = now()->subDays($days);
            $query->where(function (Builder $q) use ($cutoff): void {
                $q->whereNull('last_contact_at')
                    ->orWhere('last_contact_at', '<=', $cutoff);
            });
        }

        if (! empty($filters['never_contacted'])) {
            $query->whereNull('last_contact_at');
        }

        return $query;
    }

    /**
     * Resolve the targeted contacts collection.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Contact>
     */
    public function getContacts(array $filters = [], ?int $limit = null): Collection
    {
        $query = $this->buildQuery($filters)->with(['leads' => fn ($q) => $q->latest()]);

        if (! is_null($limit) && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Count matching contacts for the given criteria.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getCount(array $filters = []): int
    {
        return $this->buildQuery($filters)->count();
    }

    /**
     * Resolve contacts for an existing audience model and update cached count.
     *
     * @return Collection<int, Contact>
     */
    public function resolveAudienceContacts(Audience $audience): Collection
    {
        $contacts = $this->getContacts((array) $audience->filters);

        $audience->update(['cached_count' => $contacts->count()]);

        return $contacts;
    }

    /**
     * Resolve count for an audience model.
     */
    public function resolveAudienceCount(Audience $audience): int
    {
        $count = $this->getCount((array) $audience->filters);
        $audience->update(['cached_count' => $count]);

        return $count;
    }

    /**
     * Preview segmentation details and sample records.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_count: int,
     *     sample_contacts: array<int, array<string, mixed>>,
     *     criteria_summary: array<string, mixed>
     * }
     */
    public function previewSegmentation(array $filters = []): array
    {
        $count = $this->getCount($filters);
        $samples = $this->getContacts($filters, 5);

        $sampleData = $samples->map(fn (Contact $c): array => [
            'id' => $c->id,
            'name' => $c->full_name,
            'phone' => $c->phone,
            'location' => $c->location,
            'lead_source' => $c->lead_source instanceof \BackedEnum ? $c->lead_source->value : $c->lead_source,
            'last_contact_at' => $c->last_contact_at?->diffForHumans(),
        ])->all();

        return [
            'count' => $count,
            'total_count' => $count,
            'sample' => $sampleData,
            'sample_contacts' => $sampleData,
            'criteria_summary' => $filters,
        ];
    }
}
