<?php

namespace Database\Factories;

use App\Enums\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $material = fake()->randomElement(Material::values());
        $material_is_gold = strtolower($material) == strtolower(Material::GOLD->value);

        return [
            'name' => $name = fake()->workd()->unique(),
            'slug' => Str::slug($name, '-'),
            'description' => $description = fake()->realText(500),
            'short_description' => $description = fake()->realText(500),
            'cost_price' => $cost_price = fake()->randomFloat(2, 100),
            'compare_price' => $compare_price = fake()->randomFloat(2, $$cost_price + 100),
            'price' => $compare_price - fake()->randomFloat(2, 10, $compare_price - 50),
        ];
    }
}
