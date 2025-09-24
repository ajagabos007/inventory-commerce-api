<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\Store;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fake = fake('en_NG');

        $region = $fake->region();

        return [
            'name' => $name = "{$region} CBM MALL Inc",
            'slug' => SlugService::createSlug(Store::class, 'slug', $name),
            'country' => 'Nigeria',
            'city' => $region,
            'address' => "{$fake->streetAddress()} {$fake->city()}, {$region}",
            'is_warehouse' => false,
        ];
    }
}
