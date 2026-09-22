<?php

namespace Database\Factories;

use App\Enums\TitleDocument;
use App\Models\Estate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Estate>
 */
class EstateFactory extends Factory
{
    protected $model = Estate::class;

    public function definition(): array
    {
        $name = fake()->city().' '.fake()->randomElement(['Gardens', 'Haven', 'Palms', 'Heights', 'Court', 'Terraces']);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'location' => fake()->address(),
            'city' => fake()->randomElement(['Ibeju-Lekki', 'Epe', 'Lekki Phase 1', 'Maitama', 'Ikeja']),
            'state' => fake()->randomElement(['Lagos', 'Abuja (FCT)', 'Ogun']),
            'landmarks' => '5 mins from Free Trade Zone',
            'title_document' => fake()->randomElement([
                TitleDocument::GovernorsConsent->label(),
                TitleDocument::CertificateOfOccupancy->label(),
                TitleDocument::Gazette->label(),
            ]),
            'description' => fake()->paragraph(),
            'features' => ['100% Dry Table Land', 'Gated Security', 'Paved Roads'],
            'cover_image' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef',
            'status' => 'active',
            'total_land_size' => '30 Hectares',
        ];
    }
}
