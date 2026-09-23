<?php

namespace App\Models;

use App\Enums\AutomationActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Automation Action Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $workflow_id
 * @property AutomationActionType|string $action_type
 * @property array<string, mixed> $action_config
 * @property int $delay_seconds
 * @property string $delay_type
 * @property int $sort_order
 */
class AutomationAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workflow_id',
        'action_type',
        'action_config',
        'delay_seconds',
        'delay_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => AutomationActionType::class,
            'action_config' => 'array',
            'delay_seconds' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $action): void {
            if (empty($action->uuid)) {
                $action->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRunLog::class, 'action_id');
    }

    /**
     * Check if action has a deferred execution delay.
     */
    public function hasDelay(): bool
    {
        return $this->delay_seconds > 0;
    }
}
