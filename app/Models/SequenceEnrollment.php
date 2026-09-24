<?php

namespace App\Models;

use App\Enums\SequenceEnrollmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sequence Enrollment Record Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $sequence_id
 * @property int $contact_id
 * @property ?int $lead_id
 * @property SequenceEnrollmentStatus|string $status
 * @property int $current_step_number
 * @property ?Carbon $enrolled_at
 * @property ?Carbon $next_step_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $cancelled_at
 * @property ?string $cancellation_reason
 * @property ?int $enrolled_by_id
 * @property-read FollowUpSequence $sequence
 * @property-read Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?User $enrolledBy
 */
class SequenceEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'sequence_id',
        'contact_id',
        'lead_id',
        'status',
        'current_step_number',
        'next_step_id',
        'last_executed_step_id',
        'enrolled_at',
        'next_step_due_at',
        'next_step_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'enrolled_by_user_id',
        'enrolled_by_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SequenceEnrollmentStatus::class,
            'current_step_number' => 'integer',
            'enrolled_at' => 'datetime',
            'next_step_due_at' => 'datetime',
            'next_step_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $enrollment): void {
            if (empty($enrollment->uuid)) {
                $enrollment->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SequenceEnrollmentStatus::Active->value);
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SequenceStepLog::class, 'enrollment_id')->orderBy('step_number');
    }

    public function isActive(): bool
    {
        return $this->status === SequenceEnrollmentStatus::Active;
    }

    public function isCancelled(): bool
    {
        return $this->status === SequenceEnrollmentStatus::Cancelled;
    }

    public function isCompleted(): bool
    {
        return $this->status === SequenceEnrollmentStatus::Completed;
    }
}
