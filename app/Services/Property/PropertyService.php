<?php

namespace App\Services\Property;

use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\PropertyPrice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PropertyService
{
    /**
     * Get paginated properties with multifaceted filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedProperties(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Property::query()
            ->with(['estate', 'activePrice', 'primaryMedia', 'promotion'])
            ->latest('id');

        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        if (! empty($filters['estate_id'])) {
            $query->where('estate_id', $filters['estate_id']);
        }

        if (! empty($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        if (! empty($filters['availability'])) {
            $query->where('availability', $filters['availability']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['title_document'])) {
            $query->where('title_document', $filters['title_document']);
        }

        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['min_price']) || ! empty($filters['max_price'])) {
            $query->whereHas('activePrice', function (Builder $pq) use ($filters): void {
                if (! empty($filters['min_price'])) {
                    $pq->where('regular_price', '>=', (float) $filters['min_price']);
                }
                if (! empty($filters['max_price'])) {
                    $pq->where('regular_price', '<=', (float) $filters['max_price']);
                }
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Create a new property with pricing and media.
     *
     * @param  array<string, mixed>  $data
     */
    public function createProperty(array $data): Property
    {
        return DB::transaction(function () use ($data): Property {
            $property = Property::create([
                'estate_id' => $data['estate_id'] ?? null,
                'promotion_id' => $data['promotion_id'] ?? null,
                'title' => $data['title'],
                'property_type' => $data['property_type'] ?? 'land',
                'plot_size' => $data['plot_size'],
                'plot_number' => $data['plot_number'] ?? null,
                'location' => $data['location'] ?? null,
                'title_document' => $data['title_document'] ?? null,
                'description' => $data['description'] ?? null,
                'features' => $data['features'] ?? [],
                'availability' => $data['availability'] ?? 'available',
                'available_units' => (int) ($data['available_units'] ?? 1),
                'total_units' => (int) ($data['total_units'] ?? ($data['available_units'] ?? 1)),
                'status' => $data['status'] ?? 'published',
                'is_featured' => (bool) ($data['is_featured'] ?? false),
            ]);

            $this->syncPrice($property, $data);

            if (! empty($data['cover_image_url'])) {
                $property->media()->create([
                    'media_type' => 'image',
                    'file_path' => $data['cover_image_url'],
                    'file_url' => $data['cover_image_url'],
                    'caption' => $property->title,
                    'is_primary' => true,
                    'order_column' => 0,
                ]);
            }

            return $property->load(['estate', 'activePrice', 'media', 'promotion']);
        });
    }

    /**
     * Update an existing property.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProperty(Property $property, array $data): Property
    {
        return DB::transaction(function () use ($property, $data): Property {
            $property->update([
                'estate_id' => $data['estate_id'] ?? $property->estate_id,
                'promotion_id' => array_key_exists('promotion_id', $data) ? $data['promotion_id'] : $property->promotion_id,
                'title' => $data['title'] ?? $property->title,
                'property_type' => $data['property_type'] ?? $property->property_type,
                'plot_size' => $data['plot_size'] ?? $property->plot_size,
                'plot_number' => $data['plot_number'] ?? $property->plot_number,
                'location' => $data['location'] ?? $property->location,
                'title_document' => $data['title_document'] ?? $property->title_document,
                'description' => $data['description'] ?? $property->description,
                'features' => $data['features'] ?? $property->features,
                'availability' => $data['availability'] ?? $property->availability,
                'available_units' => isset($data['available_units']) ? (int) $data['available_units'] : $property->available_units,
                'total_units' => isset($data['total_units']) ? (int) $data['total_units'] : $property->total_units,
                'status' => $data['status'] ?? $property->status,
                'is_featured' => isset($data['is_featured']) ? (bool) $data['is_featured'] : $property->is_featured,
            ]);

            if (isset($data['regular_price'])) {
                $this->syncPrice($property, $data);
            }

            if (! empty($data['cover_image_url'])) {
                $primary = $property->primaryMedia;
                if ($primary) {
                    $primary->update([
                        'file_path' => $data['cover_image_url'],
                        'file_url' => $data['cover_image_url'],
                    ]);
                } else {
                    $property->media()->create([
                        'media_type' => 'image',
                        'file_path' => $data['cover_image_url'],
                        'file_url' => $data['cover_image_url'],
                        'caption' => $property->title,
                        'is_primary' => true,
                        'order_column' => 0,
                    ]);
                }
            }

            return $property->fresh(['estate', 'activePrice', 'media', 'promotion']);
        });
    }

    /**
     * Sync active pricing and generated installment schedules.
     *
     * @param  array<string, mixed>  $data
     */
    public function syncPrice(Property $property, array $data): PropertyPrice
    {
        $regularPrice = (float) ($data['regular_price'] ?? 0);
        $promoPrice = ! empty($data['promo_price']) ? (float) $data['promo_price'] : null;
        $initialDeposit = ! empty($data['initial_deposit'])
            ? (float) $data['initial_deposit']
            : round(($regularPrice * 0.20), 2); // Default 20% initial deposit

        // Build payment plan spread
        $paymentPlans = $data['payment_plans'] ?? $this->generateStandardPaymentPlans($regularPrice, $promoPrice, $initialDeposit);
        $paymentPlanSummary = $data['payment_plan_summary'] ?? $this->formatPaymentPlanSummary($paymentPlans, $initialDeposit);

        // Deactivate old active price
        $property->prices()->where('is_active', true)->update(['is_active' => false]);

        return $property->prices()->create([
            'regular_price' => $regularPrice,
            'promo_price' => $promoPrice,
            'initial_deposit' => $initialDeposit,
            'payment_plan_summary' => $paymentPlanSummary,
            'payment_plans' => $paymentPlans,
            'currency' => $data['currency'] ?? 'NGN',
            'is_active' => true,
        ]);
    }

    /**
     * Generate standard Nigerian real estate payment plan tiers (Outright, 3 Months, 6 Months, 12 Months).
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateStandardPaymentPlans(float $regularPrice, ?float $promoPrice, float $initialDeposit): array
    {
        $effective = ($promoPrice !== null && $promoPrice > 0 && $promoPrice < $regularPrice) ? $promoPrice : $regularPrice;
        $balance = max(0, $effective - $initialDeposit);

        return [
            [
                'name' => 'Outright Payment',
                'duration_months' => 0,
                'total_amount' => $effective,
                'initial_deposit' => $effective,
                'monthly_installment' => 0,
                'description' => '100% full payment with instant deed allocation and documentation',
            ],
            [
                'name' => '3 Months Installment',
                'duration_months' => 3,
                'total_amount' => $effective,
                'initial_deposit' => $initialDeposit,
                'monthly_installment' => round(($balance / 3), 2),
                'description' => 'Zero interest payment spread over 90 days',
            ],
            [
                'name' => '6 Months Installment',
                'duration_months' => 6,
                'total_amount' => round(($effective * 1.05), 2), // 5% spread convenience
                'initial_deposit' => $initialDeposit,
                'monthly_installment' => round((max(0, ($effective * 1.05) - $initialDeposit) / 6), 2),
                'description' => 'Flexible bi-monthly or monthly schedule across 6 months',
            ],
            [
                'name' => '12 Months Installment',
                'duration_months' => 12,
                'total_amount' => round(($effective * 1.10), 2), // 10% spread convenience
                'initial_deposit' => $initialDeposit,
                'monthly_installment' => round((max(0, ($effective * 1.10) - $initialDeposit) / 12), 2),
                'description' => 'Comfortable 1-year payment plan with structured milestones',
            ],
        ];
    }

    /**
     * Format a summary text of payment plans for quick customer comprehension.
     *
     * @param  array<int, array<string, mixed>>  $plans
     */
    public function formatPaymentPlanSummary(array $plans, float $initialDeposit): string
    {
        $depositText = 'Initial deposit: ₦'.number_format($initialDeposit);
        $planTexts = [];

        foreach ($plans as $plan) {
            if ($plan['duration_months'] > 0) {
                $planTexts[] = "{$plan['duration_months']} Months (₦".number_format($plan['monthly_installment']).'/mo)';
            }
        }

        return $depositText.'. Plans available: '.implode(' | ', $planTexts);
    }

    /**
     * Add media asset to a property.
     *
     * @param  array<string, mixed>  $data
     */
    public function addMedia(Property $property, array $data): PropertyMedia
    {
        if (! empty($data['is_primary'])) {
            $property->media()->update(['is_primary' => false]);
        }

        return $property->media()->create([
            'media_type' => $data['media_type'] ?? 'image',
            'file_path' => $data['file_path'],
            'file_url' => $data['file_url'] ?? null,
            'caption' => $data['caption'] ?? null,
            'is_primary' => (bool) ($data['is_primary'] ?? false),
            'order_column' => (int) ($data['order_column'] ?? $property->media()->count()),
        ]);
    }

    /**
     * Decrement available units when a property is booked or purchased.
     */
    public function recordUnitSale(Property $property, int $units = 1): Property
    {
        return DB::transaction(function () use ($property, $units): Property {
            $newUnits = max(0, $property->available_units - $units);
            $availability = $newUnits === 0 ? 'sold_out' : $property->availability;

            $property->update([
                'available_units' => $newUnits,
                'availability' => $availability,
            ]);

            return $property;
        });
    }

    /**
     * Delete property.
     */
    public function deleteProperty(Property $property): bool
    {
        return (bool) $property->delete();
    }
}
