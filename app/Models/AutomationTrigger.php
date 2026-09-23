<?php

namespace App\Models;

use App\Enums\AutomationTriggerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Automation Trigger Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $workflow_id
 * @property AutomationTriggerType|string $trigger_type
 * @property ?array<string, mixed> $filter_criteria
 * @property bool $is_active
 */
class AutomationTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workflow_id',
        'trigger_type',
        'filter_criteria',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'trigger_type' => AutomationTriggerType::class,
            'filter_criteria' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $trigger): void {
            if (empty($trigger->uuid)) {
                $trigger->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'trigger_id');
    }
}
