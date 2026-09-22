<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\PurchaseTimeline;
use App\Enums\QualificationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Sales Lead Opportunity Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $contact_id
 * @property ?int $pipeline_id
 * @property ?int $pipeline_stage_id
 * @property ?int $assigned_user_id
 * @property string $title
 * @property LeadSource $lead_source
 * @property LeadStatus $status
 * @property LeadTemperature $temperature
 * @property int $score
 * @property ?float $budget_min
 * @property ?float $budget_max
 * @property ?string $budget_range
 * @property PurchaseTimeline $purchase_timeline
 * @property ?string $preferred_location
 * @property ?string $property_interest
 * @property QualificationStatus $qualification_status
 * @property ?string $notes
 * @property ?string $lost_reason
 * @property ?\Illuminate\Support\Carbon $converted_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 * @property ?\Illuminate\Support\Carbon $deleted_at
 * @property-read Contact $contact
 * @property-read ?Pipeline $pipeline
 * @property-read ?PipelineStage $stage
 * @property-read ?User $assignedUser
 * @property-read string $formatted_budget
 * @property-read bool $is_hot
 */
class Lead extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'contact_id',
        'pipeline_id',
        'pipeline_stage_id',
        'assigned_user_id',
        'title',
        'lead_source',
        'status',
        'temperature',
        'score',
        'budget_min',
        'budget_max',
        'budget_range',
        'purchase_timeline',
        'preferred_location',
        'property_interest',
        'qualification_status',
        'notes',
        'lost_reason',
        'converted_at',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'formatted_budget',
        'is_hot',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'temperature' => LeadTemperature::class,
            'lead_source' => LeadSource::class,
            'purchase_timeline' => PurchaseTimeline::class,
            'qualification_status' => QualificationStatus::class,
            'score' => 'integer',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * Auto-generate UUID upon model creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Lead $lead): void {
            if (empty($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The contact who owns this sales opportunity.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The assigned sales representative.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * The assigned sales pipeline.
     *
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * The current sales pipeline stage.
     *
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * Audit trail and movement activities.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /**
     * Formatted budget string with Nigerian Naira (₦) symbol.
     */
    protected function formattedBudget(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->budget_min !== null && $this->budget_max !== null) {
                    return sprintf('₦%s - ₦%s', number_format($this->budget_min), number_format($this->budget_max));
                }

                if ($this->budget_min !== null) {
                    return sprintf('From ₦%s', number_format($this->budget_min));
                }

                if ($this->budget_max !== null) {
                    return sprintf('Up to ₦%s', number_format($this->budget_max));
                }

                return $this->budget_range ?? 'Budget Flexible';
            }
        );
    }

    /**
     * Helper to identify high-urgency hot leads.
     */
    protected function isHot(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->temperature === LeadTemperature::Hot
        );
    }

    /**
     * Text search scope across title, property, location, notes, and contact details.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('property_interest', 'like', "%{$term}%")
                ->orWhere('preferred_location', 'like', "%{$term}%")
                ->orWhere('notes', 'like', "%{$term}%")
                ->orWhereHas('contact', function (Builder $cq) use ($term): void {
                    $cq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Scope filter by status.
     */
    public function scopeStatus(Builder $query, LeadStatus|string $status): Builder
    {
        $value = $status instanceof LeadStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * Scope filter by temperature.
     */
    public function scopeTemperature(Builder $query, LeadTemperature|string $temperature): Builder
    {
        $value = $temperature instanceof LeadTemperature ? $temperature->value : $temperature;

        return $query->where('temperature', $value);
    }

    /**
     * Scope filter by assigned agent.
     */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId);
    }

    /**
     * Scope filter for unassigned leads.
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('assigned_user_id');
    }

    /**
     * Scope filter by lead source.
     */
    public function scopeSource(Builder $query, LeadSource|string $source): Builder
    {
        $value = $source instanceof LeadSource ? $source->value : $source;

        return $query->where('lead_source', $value);
    }

    /**
     * Scope filter by property interest.
     */
    public function scopeProperty(Builder $query, string $property): Builder
    {
        return $query->where('property_interest', 'like', "%{$property}%");
    }

    /**
     * Scope filter by creation date range or preset.
     */
    public function scopeDateRange(Builder $query, ?string $from = null, ?string $to = null, ?string $preset = null): Builder
    {
        if ($preset !== null && $preset !== '') {
            match ($preset) {
                'today' => $query->whereDate('created_at', Carbon::today()),
                'yesterday' => $query->whereDate('created_at', Carbon::yesterday()),
                'this_week' => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                'this_month' => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]),
                'last_30_days' => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
                default => null,
            };

            return $query;
        }

        if (! empty($from)) {
            $query->whereDate('created_at', '>=', Carbon::parse($from));
        }

        if (! empty($to)) {
            $query->whereDate('created_at', '<=', Carbon::parse($to));
        }

        return $query;
    }

    /**
     * Retrieve the model for a bound value (supports both numeric ID and UUID).
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
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
