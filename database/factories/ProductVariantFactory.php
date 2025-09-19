<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => $product = Product::inRandomOrder()->whereDoesntHave('variants')->first() ?? Product::factory()->create(),
            'price' => $price = $this->faker->randomFloat(2, 1500, 100000),
            'compare_price' => $price + $this->faker->randomFloat(2, 999, 90000),
            'cost_price' => $price - ($price * random_int(15, 55)/100),
            'sku' => $sku = strtoupper(Str::random(6)),
            'barcode' => 'data:image/png;base64,'.(new DNS1D)->getBarcodePNG($sku, 'c128', $w = 1, $h = 33, [0, 0, 0], true),
            'is_serialized' => $product->is_serialized,
        ];
    }
}
