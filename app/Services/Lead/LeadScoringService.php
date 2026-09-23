<?php

namespace App\Services\Lead;

use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Events\LeadScoreChanged;
use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadScoreLog;
use App\Models\LeadScoringRule;
use App\Models\User;
use Database\Seeders\LeadScoringRuleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Enterprise Configurable Lead Scoring Service.
 *
 * Coordinates CRM event-based lead scoring, dynamic rule configuration,
 * temperature threshold categorization, and event dispatching.
 */
class LeadScoringService
{
    /**
     * Record a scoring event for a lead.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordEvent(
        Lead $lead,
        string $eventKey,
        array $context = [],
        ?User $actor = null,
        string $source = 'crm_event'
    ): ?LeadScoreLog {
        /** @var ?LeadScoringRule $rule */
        $rule = LeadScoringRule::byEvent($eventKey)->first();

        // If rule does not exist in DB yet, auto-create it from system defaults if known
        if (! $rule) {
            foreach (LeadScoringRuleSeeder::DEFAULT_RULES as $default) {
                if ($default['event_key'] === $eventKey) {
                    $rule = LeadScoringRule::create($default);
                    break;
                }
            }
        }

        // If rule does not exist or is disabled, do not award points
        if (! $rule || ! $rule->is_active) {
            return null;
        }

        // Idempotency check: if rule does not allow multiple applications
        if (! $rule->allow_multiple) {
            $alreadyApplied = $lead->scoreLogs()
                ->where('event_key', $eventKey)
                ->exists();

            if ($alreadyApplied) {
                return null;
            }
        } elseif ($rule->cooldown_minutes) {
            $recentLog = $lead->scoreLogs()
                ->where('event_key', $eventKey)
                ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
                ->exists();

            if ($recentLog) {
                return null;
            }
        }

        return DB::transaction(function () use ($lead, $rule, $eventKey, $context, $actor, $source): LeadScoreLog {
            $oldScore = (int) $lead->score;
            $oldTemperature = $lead->temperature instanceof LeadTemperature
                ? $lead->temperature
                : LeadTemperature::fromScore($oldScore);

            $points = (int) $rule->points;
            $newScore = (int) max(0, min(100, $oldScore + $points));
            $newTemperature = LeadTemperature::fromScore($newScore);

            // Update lead model
            $lead->update([
                'score' => $newScore,
                'temperature' => $newTemperature,
            ]);

            // Create scoring log entry
            $log = LeadScoreLog::create([
                'lead_id' => $lead->id,
                'scoring_rule_id' => $rule->id,
                'event_key' => $eventKey,
                'points_awarded' => $points,
                'score_before' => $oldScore,
                'score_after' => $newScore,
                'temperature_before' => $oldTemperature,
                'temperature_after' => $newTemperature,
                'actor_id' => $actor?->id,
                'source' => $source,
                'metadata' => array_merge($context, [
                    'rule_name' => $rule->name,
                    'rule_points' => $rule->points,
                ]),
            ]);

            // Record CRM timeline activity
            Activity::create([
                'lead_id' => $lead->id,
                'user_id' => $actor?->id,
                'activity_type' => 'lead_score_updated',
                'description' => "Lead score updated (+{$points} pts): {$rule->name} [Score: {$newScore}/100, Temp: {$newTemperature->label()}]",
                'properties' => [
                    'event_key' => $eventKey,
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'points_awarded' => $points,
                    'score_before' => $oldScore,
                    'score_after' => $newScore,
                    'temperature_before' => $oldTemperature->value,
                    'temperature_after' => $newTemperature->value,
                    'source' => $source,
                ],
            ]);

            // Dispatch domain event
            LeadScoreChanged::dispatch(
                $lead,
                $oldScore,
                $newScore,
                $oldTemperature,
                $newTemperature,
                $eventKey,
                $rule,
                $actor
            );

            Log::info("Lead #{$lead->id} score adjusted to {$newScore} (+{$points}) via rule '{$rule->name}'", [
                'event_key' => $eventKey,
                'temperature' => $newTemperature->value,
            ]);

            return $log;
        });
    }

    /**
     * Manually adjust a lead's score by custom points delta.
     *
     * @param  array<string, mixed>  $context
     */
    public function adjustScore(
        Lead $lead,
        int $pointsDelta,
        string $reason,
        ?User $actor = null,
        array $context = []
    ): LeadScoreLog {
        return DB::transaction(function () use ($lead, $pointsDelta, $reason, $actor, $context): LeadScoreLog {
            $oldScore = (int) $lead->score;
            $oldTemperature = $lead->temperature instanceof LeadTemperature
                ? $lead->temperature
                : LeadTemperature::fromScore($oldScore);

            $newScore = (int) max(0, min(100, $oldScore + $pointsDelta));
            $newTemperature = LeadTemperature::fromScore($newScore);

            $lead->update([
                'score' => $newScore,
                'temperature' => $newTemperature,
            ]);

            $log = LeadScoreLog::create([
                'lead_id' => $lead->id,
                'scoring_rule_id' => null,
                'event_key' => 'manual_adjustment',
                'points_awarded' => $pointsDelta,
                'score_before' => $oldScore,
                'score_after' => $newScore,
                'temperature_before' => $oldTemperature,
                'temperature_after' => $newTemperature,
                'actor_id' => $actor?->id,
                'source' => 'manual_admin',
                'metadata' => array_merge($context, [
                    'reason' => $reason,
                ]),
            ]);

            Activity::create([
                'lead_id' => $lead->id,
                'user_id' => $actor?->id,
                'activity_type' => 'lead_score_updated',
                'description' => "Manual lead score adjustment ({$pointsDelta} pts): {$reason} [Score: {$newScore}/100]",
                'properties' => [
                    'event_key' => 'manual_adjustment',
                    'reason' => $reason,
                    'points_awarded' => $pointsDelta,
                    'score_before' => $oldScore,
                    'score_after' => $newScore,
                ],
            ]);

            LeadScoreChanged::dispatch(
                $lead,
                $oldScore,
                $newScore,
                $oldTemperature,
                $newTemperature,
                'manual_adjustment',
                null,
                $actor
            );

            return $log;
        });
    }

    /**
     * Inspect profile attributes of a lead and record matching initial rules.
     *
     * @return array<int, LeadScoreLog>
     */
    public function evaluateProfileEvents(Lead $lead, ?User $actor = null): array
    {
        $logs = [];

        // 1. Property Identified (+5)
        if (! empty($lead->property_id) || ! empty($lead->property_interest)) {
            $log = $this->recordEvent($lead, 'property_identified', ['property' => $lead->property_interest], $actor);
            if ($log) {
                $logs[] = $log;
            }
        }

        // 2. Location Supplied (+5)
        if (! empty($lead->preferred_location)) {
            $log = $this->recordEvent($lead, 'location_supplied', ['location' => $lead->preferred_location], $actor);
            if ($log) {
                $logs[] = $log;
            }
        }

        // 3. Budget Supplied (+10)
        if ($lead->budget_max > 0 || $lead->budget_min > 0 || ! empty($lead->budget_range)) {
            $log = $this->recordEvent($lead, 'budget_supplied', [
                'budget_min' => $lead->budget_min,
                'budget_max' => $lead->budget_max,
            ], $actor);
            if ($log) {
                $logs[] = $log;
            }
        }

        // 4. Purchase Within 30 Days (+15)
        $timelineVal = $lead->purchase_timeline instanceof PurchaseTimeline
            ? $lead->purchase_timeline->value
            : (is_string($lead->purchase_timeline) ? $lead->purchase_timeline : null);

        if ($timelineVal === 'immediate' || $lead->purchase_timeline === PurchaseTimeline::Immediate) {
            $log = $this->recordEvent($lead, 'purchase_within_30_days', ['timeline' => 'immediate'], $actor);
            if ($log) {
                $logs[] = $log;
            }
        }

        return $logs;
    }

    /**
     * Get all configurable scoring rules.
     *
     * @return Collection<int, LeadScoringRule>
     */
    public function getRules(): Collection
    {
        return LeadScoringRule::query()
            ->withCount('logs')
            ->orderBy('id')
            ->get();
    }

    /**
     * Update an existing scoring rule's points, description, or constraints.
     *
     * @param  array{name?: string, points?: int, description?: ?string, is_active?: bool, allow_multiple?: bool, cooldown_minutes?: ?int}  $data
     */
    public function updateRule(LeadScoringRule $rule, array $data): LeadScoringRule
    {
        $rule->update($data);

        Log::info("Lead scoring rule #{$rule->id} ({$rule->event_key}) updated to {$rule->points} points", [
            'is_active' => $rule->is_active,
        ]);

        return $rule;
    }

    /**
     * Toggle the active state of a scoring rule.
     */
    public function toggleRule(LeadScoringRule $rule): LeadScoringRule
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return $rule;
    }

    /**
     * Reset rules to the initial 10 defaults.
     */
    public function resetToDefaults(): void
    {
        foreach (LeadScoringRuleSeeder::DEFAULT_RULES as $rule) {
            LeadScoringRule::updateOrCreate(
                ['event_key' => $rule['event_key']],
                $rule
            );
        }
    }
}
