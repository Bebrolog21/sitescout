<?php

namespace Database\Factories;

use App\Models\ResidentialComplex;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::query()->inRandomOrder()->value('id'),
            'title' => fake()->streetName() . ' storage candidate',
            'city' => fake()->city(),
            'district' => fake()->citySuffix(),
            'address' => fake()->streetAddress(),
            'lat' => fake()->latitude(54.8, 55.1),
            'lng' => fake()->longitude(73.1, 73.6),
            'site_type' => fake()->randomElement(['yard', 'parking', 'tech_zone', 'other']),
            'area_m2' => fake()->numberBetween(120, 260),
            'status' => fake()->randomElement(['new', 'screening', 'inspection', 'scoring', 'negotiation']),
            'owner_type' => fake()->randomElement(['management_company', 'developer', 'municipality', 'private']),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
        ];
    }
}
