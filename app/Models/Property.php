<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Bamcom Real Estate Property Model.
 *
 * @property int $id
 * @property string $uuid
 * @property ?int $estate_id
 * @property ?int $promotion_id
 * @property string $title
 * @property string $slug
 * @property PropertyType|string $property_type
 * @property string $plot_size
 * @property ?string $plot_number
 * @property ?string $location
 * @property ?string $title_document
 * @property ?string $description
 * @property ?array<string> $features
 * @property PropertyStatus|string $availability
 * @property int $available_units
 * @property int $total_units
 * @property string $status
 * @property bool $is_featured
 * @property-read ?Estate $estate
 * @property-read ?Promotion $promotion
 * @property-read Collection<int, PropertyPrice> $prices
 * @property-read ?PropertyPrice $activePrice
 * @property-read Collection<int, PropertyMedia> $media
 * @property-read ?PropertyMedia $primaryMedia
 * @property-read Collection<int, Lead> $leads
 */
class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'estate_id',
        'promotion_id',
        'title',
        'slug',
        'property_type',
        'plot_size',
        'plot_number',
        'location',
        'title_document',
        'description',
        'features',
        'availability',
        'available_units',
        'total_units',
        'status',
        'is_featured',
    ];

    protected $appends = [
        'effective_price',
        'regular_price',
        'promo_price',
        'initial_deposit',
        'payment_plan_summary',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'available_units' => 'integer',
            'total_units' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            if (empty($property->uuid)) {
                $property->uuid = (string) Str::uuid();
            }
            if (empty($property->slug)) {
                $property->slug = Str::slug($property->title).'-'.Str::lower(Str::random(5));
            }
        });
    }

    /**
     * Parent estate development.
     *
     * @return BelongsTo<Estate, $this>
     */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /**
     * Active promotional campaign, if linked directly.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Historical and current pricing tiers.
     *
     * @return HasMany<PropertyPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PropertyPrice::class);
    }

    /**
     * Currently active price tier.
     *
     * @return HasOne<PropertyPrice, $this>
     */
    public function activePrice(): HasOne
    {
        return $this->hasOne(PropertyPrice::class)->where('is_active', true)->latestOfMany();
    }

    /**
     * All media assets.
     *
     * @return HasMany<PropertyMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PropertyMedia::class)->orderBy('order_column');
    }

    /**
     * Primary hero media image.
     *
     * @return HasOne<PropertyMedia, $this>
     */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(PropertyMedia::class)->where('is_primary', true);
    }

    /**
     * Associated sales leads.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Associated deals and opportunities.
     *
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class)->latest();
    }

    /**
     * Accessor: active regular price.
     */
    public function getRegularPriceAttribute(): ?float
    {
        $price = $this->relationLoaded('activePrice') ? $this->activePrice : $this->activePrice()->first();

        return $price ? (float) $price->regular_price : null;
    }

    /**
     * Accessor: active promo price.
     */
    public function getPromoPriceAttribute(): ?float
    {
        $price = $this->relationLoaded('activePrice') ? $this->activePrice : $this->activePrice()->first();

        return $price && $price->promo_price !== null ? (float) $price->promo_price : null;
    }

    /**
     * Accessor: active initial deposit.
     */
    public function getInitialDepositAttribute(): ?float
    {
        $price = $this->relationLoaded('activePrice') ? $this->activePrice : $this->activePrice()->first();

        return $price && $price->initial_deposit !== null ? (float) $price->initial_deposit : null;
    }

    /**
     * Accessor: payment plan summary.
     */
    public function getPaymentPlanSummaryAttribute(): ?string
    {
        $price = $this->relationLoaded('activePrice') ? $this->activePrice : $this->activePrice()->first();

        return $price?->payment_plan_summary;
    }

    /**
     * Accessor: effective selling price (considering promo price and active promotions).
     */
    public function getEffectivePriceAttribute(): ?float
    {
        $price = $this->relationLoaded('activePrice') ? $this->activePrice : $this->activePrice()->first();
        if (! $price) {
            return null;
        }

        $regular = (float) $price->regular_price;

        // Check if property price record explicitly specifies a valid promo price
        if ($price->promo_price !== null && (float) $price->promo_price > 0 && (float) $price->promo_price < $regular) {
            return (float) $price->promo_price;
        }

        // Check linked active promotion
        $promotion = $this->relationLoaded('promotion') ? $this->promotion : $this->promotion()->first();
        if ($promotion && $promotion->is_active) {
            $discount = $promotion->calculateDiscount($regular);

            return max(0, $regular - $discount);
        }

        return $regular;
    }

    /**
     * Formatted string of effective price (e.g. ₦15,000,000).
     */
    public function getFormattedEffectivePriceAttribute(): string
    {
        $price = $this->effective_price;
        if ($price === null) {
            return 'Price on Request';
        }

        return '₦'.number_format($price, 2);
    }

    /**
     * Effective location fallback to estate location if empty.
     */
    public function getEffectiveLocationAttribute(): string
    {
        if (! empty($this->location)) {
            return $this->location;
        }

        if ($this->estate) {
            return $this->estate->location.', '.$this->estate->state;
        }

        return 'Lagos, Nigeria';
    }

    /**
     * Effective title document fallback to estate title document if empty.
     */
    public function getEffectiveTitleDocumentAttribute(): string
    {
        if (! empty($this->title_document)) {
            return $this->title_document;
        }

        if ($this->estate && ! empty($this->estate->title_document)) {
            return $this->estate->title_document;
        }

        return 'Registered Survey';
    }

    /**
     * Check if property is available for purchase.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->availability === 'available' && $this->available_units > 0 && $this->status === 'published';
    }

    /**
     * Scope: published properties.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope: currently available units.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('availability', 'available')
            ->where('available_units', '>', 0)
            ->where('status', 'published');
    }

    /**
     * Scope: featured properties.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Text search scope.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('plot_size', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('title_document', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('estate', function (Builder $eq) use ($term): void {
                    $eq->where('name', 'like', "%{$term}%")
                        ->orWhere('location', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Resolve route binding supporting numeric ID, slug, or UUID.
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

        return $this->where('uuid', (string) $value)
            ->orWhere('slug', (string) $value)
            ->first();
    }
}
