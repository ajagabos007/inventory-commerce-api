<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['percentage', 'fixed']);

        return [
            'code' => strtoupper($this->faker->unique()->bothify('????####')),
            'name' => $this->faker->words(3, true).' Discount',
            'description' => $this->faker->realText(),
            'type' => $type,
            'value' => $type === 'percentage'
                ? $this->faker->numberBetween(5, 50)
                : $this->faker->numberBetween(5, 100),
            'minimum_order_amount' => $this->faker->optional(0.6)->randomFloat(2, 20, 200),
            'maximum_discount_amount' => $type === 'percentage'
                ? $this->faker->optional(0.4)->randomFloat(2, 10, 100)
                : null,
            'usage_limit' => $this->faker->optional(0.7)->numberBetween(10, 1000),
            'usage_limit_per_user' => $this->faker->randomElement([1, 1, 1, 2, 3, 5]),
            'usage_count' => 0,
            'is_active' => $this->faker->boolean(85),
            'valid_from' => now()->subDays($this->faker->numberBetween(0, 30)),
            'valid_until' => now()->addDays($this->faker->numberBetween(7, 90)),
        ];
    }

    /**
     * Percentage discount coupon
     */
    public function percentage(?int $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value ?? $this->faker->numberBetween(10, 50),
            'maximum_discount_amount' => $this->faker->optional(0.5)->randomFloat(2, 10, 100),
        ]);
    }

    /**
     * Fixed amount discount coupon
     */
    public function fixed(?float $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $value ?? $this->faker->numberBetween(5, 50),
            'maximum_discount_amount' => null,
        ]);
    }

    /**
     * Active coupon
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
        ]);
    }

    /**
     * Inactive coupon
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Expired coupon
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subDays(60),
            'valid_until' => now()->subDays(1),
        ]);
    }

    /**
     * Future coupon (not yet valid)
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->addDays(5),
            'valid_until' => now()->addDays(35),
        ]);
    }

    /**
     * Limited use coupon
     */
    public function limited(int $limit = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
            'usage_count' => $this->faker->numberBetween(0, $limit - 1),
        ]);
    }

    /**
     * Fully used coupon
     */
    public function fullyUsed(): static
    {
        $limit = $this->faker->numberBetween(10, 100);

        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
            'usage_count' => $limit,
        ]);
    }

    /**
     * One-time use per user coupon
     */
    public function oneTimeUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit_per_user' => 1,
        ]);
    }

    /**
     * First order coupon
     */
    public function firstOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'FIRST'.strtoupper(Str::random(4)),
            'name' => 'First Order Discount',
            'description' => 'Special discount for your first order',
            'type' => 'percentage',
            'value' => 15,
            'usage_limit_per_user' => 1,
        ]);
    }

    /**
     * Welcome coupon
     */
    public function welcome(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'WELCOME'.$this->faker->numberBetween(10, 99),
            'name' => 'Welcome Discount',
            'description' => 'Welcome to our store! Enjoy this special discount.',
            'type' => 'percentage',
            'value' => 10,
            'minimum_order_amount' => 50,
        ]);
    }

    /**
     * Holiday/Seasonal coupon
     */
    public function seasonal(string $season = 'HOLIDAY'): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => strtoupper($season).$this->faker->numberBetween(10, 99),
            'name' => ucfirst(strtolower($season)).' Sale',
            'description' => 'Special '.strtolower($season).' discount offer',
            'type' => 'percentage',
            'value' => $this->faker->numberBetween(15, 30),
            'valid_from' => now(),
            'valid_until' => now()->addDays(14),
        ]);
    }

    /**
     * Free shipping coupon
     */
    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'FREESHIP'.$this->faker->numberBetween(10, 99),
            'name' => 'Free Shipping',
            'description' => 'Get free shipping on your order',
            'type' => 'fixed',
            'value' => 10, // Assuming average shipping cost
            'minimum_order_amount' => 75,
        ]);
    }

    /**
     * VIP customer coupon
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'VIP'.strtoupper(Str::random(6)),
            'name' => 'VIP Customer Exclusive',
            'description' => 'Exclusive discount for VIP customers',
            'type' => 'percentage',
            'value' => 25,
            'usage_limit_per_user' => 3,
            'minimum_order_amount' => 100,
        ]);
    }
}
