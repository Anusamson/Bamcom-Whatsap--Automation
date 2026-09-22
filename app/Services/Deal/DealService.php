<?php

namespace App\Services\Deal;

use App\Enums\DealStatus;
use App\Events\DealLost;
use App\Events\DealWon;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DealService
{
    /**
     * Get paginated deals with multifaceted filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedDeals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Deal::query()
            ->with(['contact', 'lead', 'property.estate', 'assignedUser', 'stage', 'pipeline'])
            ->latest('id');

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (! empty($filters['pipeline_stage_id'])) {
            $query->where('pipeline_stage_id', $filters['pipeline_stage_id']);
        }

        if (! empty($filters['min_value'])) {
            $query->where('deal_value', '>=', (float) $filters['min_value']);
        }

        if (! empty($filters['max_value'])) {
            $query->where('deal_value', '<=', (float) $filters['max_value']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Compute executive pipeline metrics.
     *
     * @return array<string, mixed>
     */
    public function getDealMetrics(): array
    {
        $wonCount = Deal::won()->count();
        $lostCount = Deal::lost()->count();
        $closedCount = $wonCount + $lostCount;

        $winRate = $closedCount > 0 ? round(($wonCount / $closedCount) * 100, 1) : 0.0;

        return [
            'total_deals' => Deal::count(),
            'open_deals_count' => Deal::open()->count(),
            'open_pipeline_value' => (float) Deal::open()->sum('deal_value'),
            'won_deals_count' => $wonCount,
            'won_deals_value' => (float) Deal::won()->sum('deal_value'),
            'lost_deals_count' => $lostCount,
            'win_rate' => $winRate,
        ];
    }

    /**
     * Create a new sales deal / opportunity.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDeal(array $data, ?User $causer = null): Deal
    {
        return DB::transaction(function () use ($data, $causer): Deal {
            $contact = Contact::findOrFail($data['contact_id']);

            $pipelineId = $data['pipeline_id'] ?? null;
            $stageId = $data['pipeline_stage_id'] ?? null;

            if (empty($pipelineId)) {
                $defaultPipeline = Pipeline::where('is_default', true)->first() ?? Pipeline::first();
                $pipelineId = $defaultPipeline?->id;
            }

            if (empty($stageId) && $pipelineId) {
                $pipeline = Pipeline::find($pipelineId);
                $stageId = $pipeline?->stages()->orderBy('order_column')->first()?->id;
            }

            $title = ! empty($data['title'])
                ? $data['title']
                : "{$contact->full_name} - Real Estate Opportunity";

            $status = $data['status'] ?? DealStatus::Open->value;
            $actualCloseDate = null;
            if ($status === DealStatus::Won->value || $status === DealStatus::Lost->value) {
                $actualCloseDate = now();
            }

            $deal = Deal::create([
                'title' => $title,
                'contact_id' => $contact->id,
                'lead_id' => $data['lead_id'] ?? null,
                'property_id' => $data['property_id'] ?? null,
                'assigned_user_id' => $data['assigned_user_id'] ?? $contact->assigned_user_id,
                'pipeline_id' => $pipelineId,
                'pipeline_stage_id' => $stageId,
                'deal_value' => (float) ($data['deal_value'] ?? 0),
                'currency' => $data['currency'] ?? 'NGN',
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'actual_close_date' => $actualCloseDate,
                'status' => $status,
                'lost_reason' => $status === DealStatus::Lost->value ? ($data['lost_reason'] ?? null) : null,
                'probability' => $data['probability'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Dispatch events if created directly in won or lost state
            if ($deal->status === DealStatus::Won) {
                event(new DealWon($deal, $causer));
            } elseif ($deal->status === DealStatus::Lost) {
                event(new DealLost($deal, $deal->lost_reason, $causer));
            }

            $this->logDealActivity($deal, 'deal_created', "Opportunity '{$deal->title}' registered with value {$deal->formatted_deal_value}.", $causer);

            return $deal->load(['contact', 'lead', 'property', 'assignedUser', 'stage']);
        });
    }

    /**
     * Update an existing deal.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDeal(Deal $deal, array $data, ?User $causer = null): Deal
    {
        return DB::transaction(function () use ($deal, $data, $causer): Deal {
            $oldStatus = $deal->status;
            $newStatus = isset($data['status']) ? DealStatus::from($data['status']) : $oldStatus;

            $updatePayload = [
                'title' => $data['title'] ?? $deal->title,
                'contact_id' => $data['contact_id'] ?? $deal->contact_id,
                'lead_id' => array_key_exists('lead_id', $data) ? $data['lead_id'] : $deal->lead_id,
                'property_id' => array_key_exists('property_id', $data) ? $data['property_id'] : $deal->property_id,
                'assigned_user_id' => array_key_exists('assigned_user_id', $data) ? $data['assigned_user_id'] : $deal->assigned_user_id,
                'pipeline_id' => $data['pipeline_id'] ?? $deal->pipeline_id,
                'pipeline_stage_id' => $data['pipeline_stage_id'] ?? $deal->pipeline_stage_id,
                'deal_value' => isset($data['deal_value']) ? (float) $data['deal_value'] : $deal->deal_value,
                'currency' => $data['currency'] ?? $deal->currency,
                'expected_close_date' => array_key_exists('expected_close_date', $data) ? $data['expected_close_date'] : $deal->expected_close_date,
                'probability' => array_key_exists('probability', $data) ? $data['probability'] : $deal->probability,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $deal->notes,
            ];

            if ($newStatus !== $oldStatus) {
                $updatePayload['status'] = $newStatus;
                if ($newStatus === DealStatus::Won) {
                    $updatePayload['actual_close_date'] = now();
                    $updatePayload['lost_reason'] = null;
                } elseif ($newStatus === DealStatus::Lost) {
                    $updatePayload['actual_close_date'] = now();
                    $updatePayload['lost_reason'] = $data['lost_reason'] ?? $deal->lost_reason;
                } else {
                    $updatePayload['actual_close_date'] = null;
                    $updatePayload['lost_reason'] = null;
                }
            } elseif (isset($data['lost_reason'])) {
                $updatePayload['lost_reason'] = $data['lost_reason'];
            }

            $deal->update($updatePayload);

            // Dispatch domain events if status transitioned
            if ($oldStatus !== DealStatus::Won && $newStatus === DealStatus::Won) {
                event(new DealWon($deal, $causer));
                $this->logDealActivity($deal, 'deal_won', "Opportunity marked as CLOSED WON with value {$deal->formatted_deal_value}.", $causer);
            } elseif ($oldStatus !== DealStatus::Lost && $newStatus === DealStatus::Lost) {
                event(new DealLost($deal, $deal->lost_reason, $causer));
                $this->logDealActivity($deal, 'deal_lost', "Opportunity marked as CLOSED LOST. Reason: {$deal->lost_reason}", $causer);
            }

            return $deal->fresh(['contact', 'lead', 'property', 'assignedUser', 'stage']);
        });
    }

    /**
     * Mark an opportunity as Closed Won.
     */
    public function markWon(Deal $deal, ?User $causer = null): Deal
    {
        return DB::transaction(function () use ($deal, $causer): Deal {
            $wonStage = $deal->pipeline?->stages()->where('is_won', true)->first();

            $deal->update([
                'status' => DealStatus::Won,
                'actual_close_date' => now(),
                'lost_reason' => null,
                'pipeline_stage_id' => $wonStage ? $wonStage->id : $deal->pipeline_stage_id,
            ]);

            event(new DealWon($deal, $causer));

            $this->logDealActivity($deal, 'deal_won', "Opportunity marked as CLOSED WON with value {$deal->formatted_deal_value}.", $causer);

            return $deal->fresh(['contact', 'lead', 'property', 'assignedUser', 'stage']);
        });
    }

    /**
     * Mark an opportunity as Closed Lost with captured reason.
     */
    public function markLost(Deal $deal, string $lostReason, ?User $causer = null): Deal
    {
        return DB::transaction(function () use ($deal, $lostReason, $causer): Deal {
            $lostStage = $deal->pipeline?->stages()->where('is_lost', true)->first();

            $deal->update([
                'status' => DealStatus::Lost,
                'lost_reason' => trim($lostReason),
                'actual_close_date' => now(),
                'pipeline_stage_id' => $lostStage ? $lostStage->id : $deal->pipeline_stage_id,
            ]);

            event(new DealLost($deal, $deal->lost_reason, $causer));

            $this->logDealActivity($deal, 'deal_lost', "Opportunity marked as CLOSED LOST. Reason: {$deal->lost_reason}", $causer);

            return $deal->fresh(['contact', 'lead', 'property', 'assignedUser', 'stage']);
        });
    }

    /**
     * Reopen a closed deal.
     */
    public function reopenDeal(Deal $deal, ?User $causer = null): Deal
    {
        return DB::transaction(function () use ($deal, $causer): Deal {
            $deal->update([
                'status' => DealStatus::Open,
                'lost_reason' => null,
                'actual_close_date' => null,
            ]);

            $this->logDealActivity($deal, 'deal_reopened', 'Opportunity reopened into active pipeline.', $causer);

            return $deal->fresh(['contact', 'lead', 'property', 'assignedUser', 'stage']);
        });
    }

    /**
     * Retrieve contact-level opportunity history and financial summary.
     *
     * @return array<string, mixed>
     */
    public function getContactDealHistory(Contact $contact): array
    {
        $deals = $contact->deals()
            ->with(['property.estate', 'assignedUser', 'stage', 'pipeline'])
            ->latest('id')
            ->get();

        $wonDeals = $deals->where('status', DealStatus::Won);
        $openDeals = $deals->where('status', DealStatus::Open);
        $lostDeals = $deals->where('status', DealStatus::Lost);

        return [
            'deals' => $deals,
            'summary' => [
                'total_deals' => $deals->count(),
                'total_won_value' => (float) $wonDeals->sum('deal_value'),
                'open_opportunities_value' => (float) $openDeals->sum('deal_value'),
                'won_count' => $wonDeals->count(),
                'open_count' => $openDeals->count(),
                'lost_count' => $lostDeals->count(),
            ],
        ];
    }

    /**
     * Delete an opportunity.
     */
    public function deleteDeal(Deal $deal): bool
    {
        return (bool) $deal->delete();
    }

    /**
     * Helper to log audit activities.
     */
    protected function logDealActivity(Deal $deal, string $type, string $description, ?User $causer): void
    {
        Activity::create([
            'user_id' => $causer?->id,
            'lead_id' => $deal->lead_id,
            'deal_id' => $deal->id,
            'activity_type' => $type,
            'description' => $description,
            'properties' => [
                'deal_id' => $deal->id,
                'deal_uuid' => $deal->uuid,
                'deal_title' => $deal->title,
                'deal_value' => $deal->deal_value,
                'status' => $deal->status->value,
                'contact_id' => $deal->contact_id,
            ],
        ]);
    }
}
