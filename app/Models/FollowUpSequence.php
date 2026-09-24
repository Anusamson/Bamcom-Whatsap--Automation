<?php

namespace App\Models;

use App\Enums\SequenceEnrollmentStatus;
use App\Enums\SequenceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Follow-up Sequence Cadence Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $description
 * @property SequenceStatus|string $status
 * @property bool $is_active
 * @property bool $exit_on_deal_won
 * @property bool $exit_on_lead_lost
 * @property bool $exit_on_opt_out
 * @property bool $exit_on_reply
 * @property ?int $created_by_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class FollowUpSequence extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'trigger_type',
        'trigger_config',
        'is_active',
        'exit_on_deal_won',
        'exit_on_lead_lost',
        'exit_on_opt_out',
        'exit_on_reply',
        'created_by_user_id',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SequenceStatus::class,
            'trigger_config' => 'array',
            'is_active' => 'boolean',
            'exit_on_deal_won' => 'boolean',
            'exit_on_lead_lost' => 'boolean',
            'exit_on_opt_out' => 'boolean',
            'exit_on_reply' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sequence): void {
            if (empty($sequence->uuid)) {
                $sequence->uuid = (string) Str::uuid();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SequenceStep::class, 'sequence_id')->orderBy('step_number');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class, 'sequence_id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class, 'sequence_id')
            ->where('status', SequenceEnrollmentStatus::Active->value);
    }

    public function completedEnrollments(): HasMany
    {
        return $this->hasMany(SequenceEnrollment::class, 'sequence_id')
            ->where('status', SequenceEnrollmentStatus::Completed->value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')
            ->withDefault(fn () => $this->belongsTo(User::class, 'created_by_id')->first());
    }
}
