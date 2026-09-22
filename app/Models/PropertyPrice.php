<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Property Price Model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $property_id
 * @property float $regular_price
 * @property ?float $promo_price
 * @property ?float $initial_deposit
 * @property ?string $payment_plan_summary
 * @property ?array<int, array<string, mixed>> $payment_plans
 * @property string $currency
 * @property bool $is_active
 * @property-read Property $property
 */
class PropertyPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'property_id',
        'regular_price',
        'promo_price',
        'initial_deposit',
        'payment_plan_summary',
        'payment_plans',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'promo_price' => 'decimal:2',
            'initial_deposit' => 'decimal:2',
            'payment_plans' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $price): void {
            if (empty($price->uuid)) {
                $price->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Parent property.
     *
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the effective price (promo_price if set and lower than regular_price, else regular_price).
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->promo_price !== null && (float) $this->promo_price > 0 && (float) $this->promo_price < (float) $this->regular_price) {
            return (float) $this->promo_price;
        }

        return (float) $this->regular_price;
    }
}
