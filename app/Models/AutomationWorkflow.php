<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Automation Workflow Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property string $status
 * @property bool $is_active
 * @property bool $allow_multiple_runs_per_subject
 * @property ?int $prevent_duplicate_window_seconds
 * @property ?int $created_by_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class AutomationWorkflow extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'is_active',
        'allow_multiple_runs_per_subject',
        'prevent_duplicate_window_seconds',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_multiple_runs_per_subject' => 'boolean',
            'prevent_duplicate_window_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $workflow): void {
            if (empty($workflow->uuid)) {
                $workflow->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Filter active workflows.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    /**
     * Associated triggers.
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(AutomationTrigger::class, 'workflow_id');
    }

    /**
     * Associated evaluation conditions.
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(AutomationCondition::class, 'workflow_id')->orderBy('sort_order');
    }

    /**
     * Associated actions.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class, 'workflow_id')->orderBy('sort_order');
    }

    /**
     * Associated execution runs.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'workflow_id');
    }

    /**
     * Workflow creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
