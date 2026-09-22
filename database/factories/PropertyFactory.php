<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\TitleDocument;
use App\Models\Estate;
use App\Models\Property;
use App\Models\PropertyPrice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $title = fake()->streetName().' Plot '.fake()->numberBetween(1, 100);

        return [
            'uuid' => (string) Str::uuid(),
            'estate_id' => Estate::factory(),
            'promotion_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'property_type' => fake()->randomElement(PropertyType::values()),
            'plot_size' => fake()->randomElement(['500sqm', '300sqm', '600sqm', '1,000sqm']),
            'plot_number' => 'BLK-'.fake()->numberBetween(1, 20).'-P'.fake()->numberBetween(1, 50),
            'location' => fake()->address(),
            'title_document' => fake()->randomElement([
                TitleDocument::GovernorsConsent->label(),
                TitleDocument::CertificateOfOccupancy->label(),
                TitleDocument::Gazette->label(),
            ]),
            'description' => fake()->paragraph(),
            'features' => ['Dry Land', 'Perimeter Fence', 'Solar Lights'],
            'availability' => PropertyStatus::Available->value,
            'available_units' => fake()->numberBetween(2, 20),
            'total_units' => fake()->numberBetween(20, 30),
            'status' => 'published',
            'is_featured' => fake()->boolean(30),
        ];
    }

    /**
     * Configure factory to automatically attach an active price record.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Property $property): void {
            if ($property->prices()->count() === 0) {
                $regular = 15000000.00;
                $promo = 13500000.00;
                $deposit = 3000000.00;

                PropertyPrice::create([
                    'property_id' => $property->id,
                    'regular_price' => $regular,
                    'promo_price' => $promo,
                    'initial_deposit' => $deposit,
                    'payment_plan_summary' => 'Initial deposit: ₦3,000,000. Plans available: 3 Months | 6 Months | 12 Months',
                    'payment_plans' => [
                        [
                            'name' => 'Outright Payment',
                            'duration_months' => 0,
                            'total_amount' => $promo,
                            'initial_deposit' => $promo,
                            'monthly_installment' => 0,
                        ],
                        [
                            'name' => '3 Months Installment',
                            'duration_months' => 3,
                            'total_amount' => $promo,
                            'initial_deposit' => $deposit,
                            'monthly_installment' => ($promo - $deposit) / 3,
                        ],
                    ],
                    'currency' => 'NGN',
                    'is_active' => true,
                ]);
            }
        });
    }

    public function soldOut(): static
    {
        return $this->state(fn () => [
            'availability' => PropertyStatus::SoldOut->value,
            'available_units' => 0,
        ]);
    }
}
