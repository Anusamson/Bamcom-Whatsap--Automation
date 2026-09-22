<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Property Promotion Model.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property ?string $code
 * @property string $discount_type
 * @property float $discount_value
 * @property ?string $description
 * @property ?string $banner_url
 * @property ?Carbon $start_date
 * @property ?Carbon $end_date
 * @property bool $is_active
 * @property-read Collection<int, Property> $properties
 */
class Promotion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'code',
        'discount_type',
        'discount_value',
        'description',
        'banner_url',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $promo): void {
            if (empty($promo->uuid)) {
                $promo->uuid = (string) Str::uuid();
            }
            if (empty($promo->slug)) {
                $promo->slug = Str::slug($promo->name);
            }
        });
    }

    /**
     * Properties linked to this promotion.
     *
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Scope for active promotions currently valid.
     */
    public function scopeActive(Builder $query): Builder
    {
        $today = Carbon::today();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $today);
            })
            ->where(function (Builder $q) use ($today): void {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });
    }

    /**
     * Calculate discount amount on a given regular price.
     */
    public function calculateDiscount(float $regularPrice): float
    {
        if ($this->discount_type === 'percentage') {
            return round(($regularPrice * ((float) $this->discount_value / 100)), 2);
        }

        return min($regularPrice, (float) $this->discount_value);
    }
}
