<?php

namespace App\Models;

use App\Enums\AutomationRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Automation Workflow Execution Run Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $workflow_id
 * @property ?int $trigger_id
 * @property string $subject_type
 * @property int $subject_id
 * @property ?string $idempotency_key
 * @property AutomationRunStatus|string $status
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?array<string, mixed> $trigger_payload
 * @property ?string $error_message
 * @property-read ?Model $subject
 */
class AutomationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'workflow_id',
        'trigger_id',
        'subject_type',
        'subject_id',
        'idempotency_key',
        'status',
        'started_at',
        'completed_at',
        'trigger_payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => AutomationRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'trigger_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (empty($run->uuid)) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(AutomationTrigger::class, 'trigger_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationRunLog::class, 'run_id')->orderBy('id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
