<?php

namespace App\Services\Lead;

use App\Enums\LeadStatus;
use App\Events\PipelineStageChanged;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;

class PipelineService extends BaseService
{
    /**
     * Retrieve the default sales pipeline.
     */
    public function getDefaultPipeline(): ?Pipeline
    {
        return Pipeline::query()
            ->with(['stages'])
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('order_column')
            ->first();
    }

    /**
     * Retrieve all active pipelines for selector navigation.
     *
     * @return Collection<int, Pipeline>
     */
    public function getAllActivePipelines(): Collection
    {
        return Pipeline::query()
            ->withCount('leads')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('order_column')
            ->get();
    }

    /**
     * Find a pipeline by ID or UUID.
     */
    public function findPipeline(int|string $identifier): ?Pipeline
    {
        if (is_numeric($identifier)) {
            return Pipeline::query()->with('stages')->find((int) $identifier);
        }

        return Pipeline::query()->with('stages')->where('uuid', $identifier)->first();
    }

    /**
     * Assemble structured Kanban board data including filtered leads per column and financial aggregations.
     *
     * @param  array{agent?: ?mixed, temperature?: ?string, search?: ?string, date?: ?string, date_from?: ?string, date_to?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function getKanbanBoardData(?Pipeline $pipeline = null, array $filters = []): array
    {
        $pipeline = $pipeline ?? $this->getDefaultPipeline();

        if (! $pipeline) {
            return [
                'pipeline' => null,
                'pipelines' => [],
                'columns' => [],
                'summary' => [
                    'total_leads' => 0,
                    'total_value' => 0,
                    'won_leads' => 0,
                    'hot_leads' => 0,
                ],
            ];
        }

        $stages = $pipeline->stages()->get();
        $stageIds = $stages->pluck('id')->all();

        // Build lead query filtered by criteria
        $leadQuery = Lead::query()
            ->with(['contact', 'assignedUser.profile'])
            ->where(function ($q) use ($pipeline, $stageIds): void {
                $q->whereIn('pipeline_stage_id', $stageIds)
                    ->orWhere(function ($fallbackQ) use ($pipeline): void {
                        if ($pipeline->is_default) {
                            $fallbackQ->whereNull('pipeline_id')
                                ->orWhereNull('pipeline_stage_id');
                        }
                    });
            });

        // Search term filter
        if (! empty($filters['search'])) {
            $leadQuery->search((string) $filters['search']);
        }

        // Temperature filter
        if (! empty($filters['temperature'])) {
            $leadQuery->temperature($filters['temperature']);
        }

        // Agent / Representative filter
        $agentFilter = $filters['agent'] ?? $filters['assigned_user_id'] ?? null;
        if ($agentFilter !== null && $agentFilter !== '') {
            if ($agentFilter === 'unassigned') {
                $leadQuery->unassigned();
            } else {
                $leadQuery->assignedTo((int) $agentFilter);
            }
        }

        // Date range filter
        $datePreset = $filters['date'] ?? null;
        $dateFrom = $filters['date_from'] ?? $filters['from_date'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['to_date'] ?? null;
        if ($datePreset || $dateFrom || $dateTo) {
            $leadQuery->dateRange($dateFrom, $dateTo, $datePreset);
        }

        $leads = $leadQuery->latest('updated_at')->get();

        // Group leads by stage id
        $leadsByStage = [];
        $firstStageId = $stages->first()?->id;

        foreach ($leads as $lead) {
            $targetStageId = $lead->pipeline_stage_id ?? $firstStageId;
            $leadsByStage[$targetStageId][] = $lead;
        }

        $totalPipelineValue = 0.0;
        $wonLeadsCount = 0;
        $hotLeadsCount = 0;

        $columns = [];
        foreach ($stages as $stage) {
            $stageLeads = $leadsByStage[$stage->id] ?? [];
            $stageValue = 0.0;

            foreach ($stageLeads as $l) {
                $val = (float) ($l->budget_max ?? $l->budget_min ?? 0);
                $stageValue += $val;
                $totalPipelineValue += $val;

                if ($l->temperature?->value === 'hot') {
                    $hotLeadsCount++;
                }
            }

            if ($stage->is_won) {
                $wonLeadsCount += count($stageLeads);
            }

            $columns[] = [
                'stage' => [
                    'id' => $stage->id,
                    'uuid' => $stage->uuid,
                    'name' => $stage->name,
                    'slug' => $stage->slug,
                    'order_column' => $stage->order_column,
                    'color' => $stage->color,
                    'probability' => $stage->probability,
                    'is_won' => $stage->is_won,
                    'is_lost' => $stage->is_lost,
                ],
                'leads' => $stageLeads,
                'count' => count($stageLeads),
                'total_value' => $stageValue,
                'formatted_value' => $this->formatCurrency($stageValue),
            ];
        }

        $allPipelines = $this->getAllActivePipelines();

        return [
            'pipeline' => [
                'id' => $pipeline->id,
                'uuid' => $pipeline->uuid,
                'name' => $pipeline->name,
                'description' => $pipeline->description,
                'is_default' => $pipeline->is_default,
            ],
            'pipelines' => $allPipelines->map(fn (Pipeline $p): array => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'name' => $p->name,
                'is_default' => $p->is_default,
                'leads_count' => $p->leads_count,
            ])->values()->all(),
            'columns' => $columns,
            'summary' => [
                'total_leads' => $leads->count(),
                'total_value' => $totalPipelineValue,
                'formatted_total_value' => $this->formatCurrency($totalPipelineValue),
                'won_leads' => $wonLeadsCount,
                'hot_leads' => $hotLeadsCount,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Move a lead to a new pipeline stage, record an activity, and dispatch PipelineStageChanged event.
     */
    public function moveLeadStage(Lead $lead, PipelineStage $newStage, ?User $causer = null): Lead
    {
        return $this->transaction(function () use ($lead, $newStage, $causer): Lead {
            $previousStage = $lead->stage;
            $previousStageId = $lead->pipeline_stage_id;

            // If already on this stage, no-op
            if ($previousStageId === $newStage->id) {
                return $lead;
            }

            $causerName = $causer ? $causer->name : 'System';
            $previousStageName = $previousStage ? $previousStage->name : 'Unassigned Stage';
            $description = sprintf("Moved lead from '%s' to '%s' by %s.", $previousStageName, $newStage->name, $causerName);

            // 1. Create activity record
            Activity::create([
                'user_id' => $causer?->id,
                'lead_id' => $lead->id,
                'activity_type' => 'stage_change',
                'description' => $description,
                'properties' => [
                    'old_stage_id' => $previousStage?->id,
                    'old_stage_name' => $previousStageName,
                    'new_stage_id' => $newStage->id,
                    'new_stage_name' => $newStage->name,
                    'pipeline_id' => $newStage->pipeline_id,
                    'previous_probability' => $previousStage?->probability ?? 0,
                    'new_probability' => $newStage->probability,
                ],
            ]);

            // 2. Update lead's stage and pipeline
            $lead->pipeline_id = $newStage->pipeline_id;
            $lead->pipeline_stage_id = $newStage->id;

            // Synchronize status for won / lost milestones
            if ($newStage->is_won) {
                $lead->status = LeadStatus::Won;
                $lead->converted_at = now();
            } elseif ($newStage->is_lost) {
                $lead->status = LeadStatus::Lost;
            }

            $lead->save();

            // 3. Dispatch PipelineStageChanged event
            PipelineStageChanged::dispatch($lead, $previousStage, $newStage, $causer);

            $this->logInfo('Lead moved to new pipeline stage', [
                'lead_id' => $lead->id,
                'stage_id' => $newStage->id,
                'stage_name' => $newStage->name,
                'causer_id' => $causer?->id,
            ]);

            return $lead->fresh(['contact', 'assignedUser.profile', 'stage', 'pipeline']);
        });
    }

    /**
     * Format currency figures in Nigerian Naira (₦).
     */
    protected function formatCurrency(float $amount): string
    {
        if ($amount >= 1000000000) {
            return sprintf('₦%.2fB', $amount / 1000000000);
        }

        if ($amount >= 1000000) {
            return sprintf('₦%.1fM', $amount / 1000000);
        }

        if ($amount >= 1000) {
            return sprintf('₦%.0fK', $amount / 1000);
        }

        return sprintf('₦%s', number_format($amount));
    }
}
