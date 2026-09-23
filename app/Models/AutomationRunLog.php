<?php

namespace App\Models;

use App\Enums\AutomationActionType;
use App\Enums\AutomationLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Automation Run Action Audit Log Model.
 *
 * @property int $id
 * @property int $run_id
 * @property ?int $action_id
 * @property AutomationActionType|string $action_type
 * @property AutomationLogStatus|string $status
 * @property ?array<string, mixed> $input_payload
 * @property ?array<string, mixed> $output_payload
 * @property ?string $error_message
 * @property ?Carbon $scheduled_at
 * @property ?Carbon $executed_at
 */
class AutomationRunLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'action_id',
        'action_type',
        'status',
        'input_payload',
        'output_payload',
        'error_message',
        'scheduled_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => AutomationActionType::class,
            'status' => AutomationLogStatus::class,
            'input_payload' => 'array',
            'output_payload' => 'array',
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(AutomationAction::class, 'action_id');
    }
}
