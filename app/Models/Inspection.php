<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Site Inspection Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $contact_id
 * @property ?int $lead_id
 * @property ?int $property_id
 * @property ?string $estate_name
 * @property ?int $representative_id
 * @property InspectionStatus $status
 * @property Carbon $inspection_date
 * @property string $inspection_time
 * @property string $meeting_point
 * @property ?string $customer_notes
 * @property ?string $sales_notes
 * @property ?string $outcome
 * @property ?int $created_by_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 * @property-read Contact $contact
 * @property-read ?Lead $lead
 * @property-read ?Property $property
 * @property-read ?User $representative
 * @property-read ?User $creator
 * @property-read string $target_title
 * @property-read string $formatted_date_time
 */
class Inspection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'contact_id',
        'lead_id',
        'property_id',
        'estate_name',
        'representative_id',
        'status',
        'inspection_date',
        'inspection_time',
        'meeting_point',
        'customer_notes',
        'sales_notes',
        'outcome',
        'created_by_id',
    ];

    protected $casts = [
        'status' => InspectionStatus::class,
        'inspection_date' => 'date:Y-m-d',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Inspection $inspection): void {
            if (empty($inspection->uuid)) {
                $inspection->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * The prospective client for this inspection.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Associated CRM sales opportunity lead.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Target property to be inspected.
     *
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Assigned company representative / field agent.
     *
     * @return BelongsTo<User, $this>
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    /**
     * User who scheduled or logged this inspection.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Scope for active inspections that occupy schedules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InspectionStatus::Requested->value,
            InspectionStatus::Scheduled->value,
            InspectionStatus::Confirmed->value,
            InspectionStatus::Rescheduled->value,
        ]);
    }

    /**
     * Scope upcoming inspections (today or in future).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('inspection_date', '>=', now()->toDateString())
            ->orderBy('inspection_date')
            ->orderBy('inspection_time');
    }

    /**
     * Scope for a specific representative.
     */
    public function scopeForRepresentative(Builder $query, int $userId): Builder
    {
        return $query->where('representative_id', $userId);
    }

    /**
     * Scope for a specific date.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('inspection_date', $date);
    }

    /**
     * Scope between dates.
     */
    public function scopeBetweenDates(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('inspection_date', [$start, $end]);
    }

    /**
     * Scope by status filter.
     */
    public function scopeByStatus(Builder $query, string|InspectionStatus $status): Builder
    {
        $val = $status instanceof InspectionStatus ? $status->value : $status;

        return $query->where('status', $val);
    }

    /**
     * Resolves human-friendly target display title (Property title or estate name).
     */
    protected function targetTitle(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->property) {
                    return $this->property->title.($this->property->estate ? ' - '.$this->property->estate->name : '');
                }

                return $this->estate_name ?: 'Bamcom Real Estate Site Inspection';
            }
        );
    }

    /**
     * Resolves human-readable date and time string.
     */
    protected function formattedDateTime(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf(
                '%s at %s',
                $this->inspection_date ? $this->inspection_date->format('l, M j, Y') : 'TBD',
                $this->inspection_time ?: '10:00 AM'
            )
        );
    }
}
