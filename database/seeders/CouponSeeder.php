<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(Coupon::query()->exists()) {
            $this->command->info('✅ Skipped seeded successfully!');
        }

        // 1. Welcome/First Order Coupons
        Coupon::factory()->welcome()->create([
            'code' => 'WELCOME10',
        ]);

        Coupon::factory()->firstOrder()->create([
            'code' => 'FIRST15',
        ]);

        // 2. Percentage Discount Coupons
        Coupon::factory()->percentage(10)->active()->create([
            'code' => 'SAVE10',
            'name' => 'Save 10% Discount',
            'minimum_order_amount' => 50,
        ]);

        Coupon::factory()->percentage(15)->active()->create([
            'code' => 'SAVE15',
            'name' => 'Save 15% Discount',
            'minimum_order_amount' => 100,
        ]);

        Coupon::factory()->percentage(20)->active()->create([
            'code' => 'SAVE20',
            'name' => 'Save 20% Discount',
            'minimum_order_amount' => 150,
            'maximum_discount_amount' => 50,
        ]);

        Coupon::factory()->percentage(25)->active()->create([
            'code' => 'MEGA25',
            'name' => 'Mega 25% Off',
            'minimum_order_amount' => 200,
            'maximum_discount_amount' => 75,
            'usage_limit' => 500,
        ]);

        // 3. Fixed Amount Coupons
        Coupon::factory()->fixed(5)->active()->create([
            'code' => 'FIVE',
            'name' => '$5 Off Your Order',
            'minimum_order_amount' => 25,
        ]);

        Coupon::factory()->fixed(10)->active()->create([
            'code' => 'TEN',
            'name' => '$10 Off Your Order',
            'minimum_order_amount' => 50,
        ]);

        Coupon::factory()->fixed(25)->active()->create([
            'code' => 'TWENTYFIVE',
            'name' => '$25 Off Your Order',
            'minimum_order_amount' => 100,
        ]);

        // 4. Free Shipping Coupon
        Coupon::factory()->freeShipping()->create([
            'code' => 'FREESHIP',
        ]);

        // 5. Seasonal/Holiday Coupons
        Coupon::factory()->seasonal('SUMMER')->create();
        Coupon::factory()->seasonal('WINTER')->create();
        Coupon::factory()->seasonal('BLACKFRIDAY')->create([
            'value' => 40,
            'usage_limit' => 1000,
        ]);

        // 6. VIP Coupons
        Coupon::factory()->vip()->create([
            'code' => 'VIPGOLD',
        ]);

        // 7. Limited Time / Flash Sale Coupons
        Coupon::factory()->active()->limited(50)->create([
            'code' => 'FLASH50',
            'name' => 'Flash Sale - Limited to 50 Uses',
            'type' => 'percentage',
            'value' => 30,
            'valid_until' => now()->addDays(3),
        ]);

        // 8. Bundle/Category Specific (you can add metadata)
        Coupon::factory()->active()->create([
            'code' => 'BUNDLE20',
            'name' => 'Bundle Deal 20% Off',
            'type' => 'percentage',
            'value' => 20,
            'minimum_order_amount' => 75,
        ]);

        // 9. Referral Coupon
        Coupon::factory()->active()->oneTimeUse()->create([
            'code' => 'REFER15',
            'name' => 'Referral Bonus',
            'description' => 'Discount for referred customers',
            'type' => 'percentage',
            'value' => 15,
        ]);

        // 10. Expired Coupons (for testing)
        Coupon::factory()->expired()->create([
            'code' => 'EXPIRED10',
            'name' => 'Expired Coupon',
        ]);

        // 11. Future Coupons (scheduled)
        Coupon::factory()->future()->create([
            'code' => 'UPCOMING20',
            'name' => 'Upcoming Sale',
            'type' => 'percentage',
            'value' => 20,
        ]);

        // 12. Inactive Coupons
        Coupon::factory()->inactive()->create([
            'code' => 'INACTIVE15',
            'name' => 'Inactive Coupon',
        ]);

        // 13. Fully Used Coupons (for testing)
        Coupon::factory()->fullyUsed()->create([
            'code' => 'SOLD OUT',
            'name' => 'Sold Out Coupon',
        ]);

        // 14. Generate random coupons for variety
        Coupon::factory()
            ->count(10)
            ->active()
            ->create();

        // 15. Generate some percentage coupons
        Coupon::factory()
            ->count(5)
            ->percentage()
            ->active()
            ->create();

        // 16. Generate some fixed coupons
        Coupon::factory()
            ->count(5)
            ->fixed()
            ->active()
            ->create();

        $this->command->info('✅ Coupons seeded successfully!');
        $this->command->info('📊 Total coupons created: ' . Coupon::count());
        $this->command->info('✅ Active coupons: ' . Coupon::active()->count());
        $this->command->info('📅 Valid coupons: ' . Coupon::valid()->count());
    }
}
