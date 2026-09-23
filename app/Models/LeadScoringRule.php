<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Configurable CRM Lead Scoring Rule.
 *
 * @property int $id
 * @property string $name
 * @property string $event_key
 * @property string $category
 * @property int $points
 * @property ?string $description
 * @property bool $is_active
 * @property bool $allow_multiple
 * @property ?int $cooldown_minutes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, LeadScoreLog> $logs
 */
class LeadScoringRule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'event_key',
        'category',
        'points',
        'description',
        'is_active',
        'allow_multiple',
        'cooldown_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'is_active' => 'boolean',
            'allow_multiple' => 'boolean',
            'cooldown_minutes' => 'integer',
        ];
    }

    /**
     * All logs/history recorded under this rule.
     *
     * @return HasMany<LeadScoreLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(LeadScoreLog::class, 'scoring_rule_id');
    }

    /**
     * Scope for active rules only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by event key.
     */
    public function scopeByEvent(Builder $query, string $eventKey): Builder
    {
        return $query->where('event_key', $eventKey);
    }
}
