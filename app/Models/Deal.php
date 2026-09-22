<?php

namespace App\Models;

use App\Enums\DealStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Sales Opportunity / Deal Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property int $contact_id
 * @property ?int $lead_id
 * @property ?int $property_id
 * @property ?int $assigned_user_id
 * @property ?int $pipeline_id
 * @property ?int $pipeline_stage_id
 * @property float $deal_value
 * @property string $currency
 * @property ?Carbon $expected_close_date
 * @property ?Carbon $actual_close_date
 * @property DealStatus $status
 * @property ?string $lost_reason
 * @property ?int $probability
 * @property ?string $notes
 * @property-read Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?Property $property
 * @property-read ?User $assignedUser
 * @property-read ?Pipeline $pipeline
 * @property-read ?PipelineStage $stage
 * @property-read Collection<int, Activity> $activities
 * @property-read string $formatted_deal_value
 * @property-read bool $is_won
 * @property-read bool $is_lost
 * @property-read bool $is_open
 */
class Deal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'deals';

    protected $fillable = [
        'uuid',
        'title',
        'contact_id',
        'lead_id',
        'property_id',
        'assigned_user_id',
        'pipeline_id',
        'pipeline_stage_id',
        'deal_value',
        'currency',
        'expected_close_date',
        'actual_close_date',
        'status',
        'lost_reason',
        'probability',
        'notes',
    ];

    protected $appends = [
        'formatted_deal_value',
        'is_won',
        'is_lost',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'deal_value' => 'decimal:2',
            'expected_close_date' => 'date',
            'actual_close_date' => 'datetime',
            'probability' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deal): void {
            if (empty($deal->uuid)) {
                $deal->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Associated customer/client contact.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Associated sales lead, if converted or linked from lead intake.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Real estate property or plot associated with this opportunity.
     *
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Assigned sales representative or account executive.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * The sales pipeline this deal belongs to.
     *
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Current sales pipeline stage.
     *
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * Audit trail and activity logs for this deal.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /**
     * Accessor: Formatted deal value in Nigerian Naira (₦).
     */
    public function getFormattedDealValueAttribute(): string
    {
        return '₦'.number_format((float) $this->deal_value, 2);
    }

    /**
     * Accessor: Is deal closed won?
     */
    public function getIsWonAttribute(): bool
    {
        return $this->status === DealStatus::Won;
    }

    /**
     * Accessor: Is deal closed lost?
     */
    public function getIsLostAttribute(): bool
    {
        return $this->status === DealStatus::Lost;
    }

    /**
     * Accessor: Is deal actively open?
     */
    public function getIsOpenAttribute(): bool
    {
        return $this->status === DealStatus::Open;
    }

    /**
     * Scope: open opportunities.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', DealStatus::Open->value);
    }

    /**
     * Scope: closed won opportunities.
     */
    public function scopeWon(Builder $query): Builder
    {
        return $query->where('status', DealStatus::Won->value);
    }

    /**
     * Scope: closed lost opportunities.
     */
    public function scopeLost(Builder $query): Builder
    {
        return $query->where('status', DealStatus::Lost->value);
    }

    /**
     * Text search scope across title, contact, property, and representative.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('lost_reason', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('contact', function (Builder $cq) use ($term): void {
                    $cq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                })
                ->orWhereHas('property', function (Builder $pq) use ($term): void {
                    $pq->where('title', 'like', "%{$term}%")
                        ->orWhere('plot_size', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%");
                })
                ->orWhereHas('assignedUser', function (Builder $uq) use ($term): void {
                    $uq->where('name', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Resolve route binding supporting numeric ID or UUID.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        if (is_numeric($value)) {
            return $this->where('id', (int) $value)->first();
        }

        return $this->where('uuid', (string) $value)->first();
    }
}
