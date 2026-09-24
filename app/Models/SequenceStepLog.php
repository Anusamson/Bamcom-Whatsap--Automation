<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sequence Step Execution Audit Log Model.
 *
 * @property int $id
 * @property int $enrollment_id
 * @property ?int $sequence_step_id
 * @property int $step_number
 * @property string $status
 * @property ?array<string, mixed> $verification_results
 * @property ?array<string, mixed> $actions_summary
 * @property ?string $skip_reason
 * @property ?string $error_message
 * @property ?Carbon $scheduled_at
 * @property ?Carbon $executed_at
 */
class SequenceStepLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_enrollment_id',
        'enrollment_id',
        'sequence_step_id',
        'step_number',
        'status',
        'verification_results',
        'actions_summary',
        'skip_reason',
        'error_message',
        'scheduled_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'verification_results' => 'array',
            'actions_summary' => 'array',
            'scheduled_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(SequenceEnrollment::class, 'enrollment_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(SequenceStep::class, 'sequence_step_id');
    }
}
