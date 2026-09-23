<?php

namespace App\Models;

use App\Enums\LeadTemperature;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historical record of lead score progression and rule executions.
 *
 * @property int $id
 * @property int $lead_id
 * @property ?int $scoring_rule_id
 * @property string $event_key
 * @property int $points_awarded
 * @property int $score_before
 * @property int $score_after
 * @property LeadTemperature $temperature_before
 * @property LeadTemperature $temperature_after
 * @property ?int $actor_id
 * @property string $source
 * @property ?array<string, mixed> $metadata
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Lead $lead
 * @property-read ?LeadScoringRule $rule
 * @property-read ?User $actor
 */
class LeadScoreLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'scoring_rule_id',
        'event_key',
        'points_awarded',
        'score_before',
        'score_after',
        'temperature_before',
        'temperature_after',
        'actor_id',
        'source',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_awarded' => 'integer',
            'score_before' => 'integer',
            'score_after' => 'integer',
            'temperature_before' => LeadTemperature::class,
            'temperature_after' => LeadTemperature::class,
            'metadata' => 'array',
        ];
    }

    /**
     * The lead associated with this scoring log.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The scoring rule applied.
     *
     * @return BelongsTo<LeadScoringRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(LeadScoringRule::class, 'scoring_rule_id');
    }

    /**
     * User or agent who performed the action, if applicable.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
