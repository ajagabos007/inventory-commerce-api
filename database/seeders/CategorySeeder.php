<?php

namespace Database\Seeders;

use App\Models\Category;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        if (Category::exists()) {
            return;
        }

        $categories = [
            'Electronics' => [
                'Mobiles & Tablets' => ['Smartphones', 'Feature Phones', 'Tablets'],
                'Computers & Laptops' => ['Laptops', 'Desktops & All-in-Ones', 'Computer Accessories'],
                'Audio & Video' => ['Headphones & Earbuds', 'Bluetooth Speakers', 'Televisions', 'Home Theatre Systems'],
                'Cameras & Photography' => ['DSLR Cameras', 'Mirrorless Cameras', 'Lenses & Tripods', 'Drones'],
            ],

            'Fashion' => [
                'Men’s Fashion' => ['T-Shirts & Polos', 'Shirts', 'Jeans & Trousers', 'Shoes & Sneakers', 'Watches'],
                'Women’s Fashion' => ['Dresses', 'Tops & Blouses', 'Jeans & Trousers', 'Handbags', 'Jewellery'],
                'Kids & Babies' => ['Baby Clothing', 'Boys’ Clothing', 'Girls’ Clothing', 'Shoes'],
            ],

            'Home & Kitchen' => [
                'Furniture' => ['Sofas & Couches', 'Beds & Mattresses', 'Tables & Desks', 'Wardrobes'],
                'Appliances' => ['Refrigerators', 'Washing Machines', 'Microwaves', 'Air Conditioners'],
                'Kitchen & Dining' => ['Cookware', 'Bakeware', 'Kitchen Tools & Gadgets', 'Dinner Sets'],
            ],

            'Health & Beauty' => [
                'Skincare' => ['Face Creams & Lotions', 'Sunscreens', 'Cleansers'],
                'Haircare' => ['Shampoos', 'Conditioners', 'Hair Oils'],
                'Makeup' => ['Foundations', 'Lipsticks', 'Eyeliners'],
                'Fragrances' => ['Perfumes', 'Body Sprays'],
            ],

            'Sports & Outdoors' => [
                'Fitness' => ['Gym Equipment', 'Dumbbells', 'Yoga Mats'],
                'Outdoor & Adventure' => ['Bicycles', 'Camping Gear', 'Hiking Boots'],
                'Team Sports' => ['Football', 'Basketball', 'Cricket'],
            ],

            'Books & Media' => [
                'Books' => ['Fiction', 'Non-Fiction', 'Children’s Books', 'Educational & Academic'],
                'Movies & Music' => ['DVDs', 'CDs & Vinyl', 'Streaming Gift Cards'],
            ],
        ];

        foreach ($categories as $main => $subs) {
            $mainCategory = Category::create([
                'id' => Str::uuid(),
                'name' => $main,
                'slug' => SlugService::createSlug(Category::class, 'slug', $main),
                'parent_id' => null,
            ]);

            foreach ($subs as $sub => $children) {
                // if $children is array of strings, treat as leaf
                $subCategory = Category::create([
                    'id' => Str::uuid(),
                    'name' => $sub,
                    'slug' => SlugService::createSlug(Category::class, 'slug', $sub),
                    'parent_id' => $mainCategory->id,
                ]);

                foreach ($children as $child) {
                    Category::create([
                        'id' => Str::uuid(),
                        'name' => $child,
                        'slug' => SlugService::createSlug(Category::class, 'slug', $child),
                        'parent_id' => $subCategory->id,
                    ]);
                }
            }
        }
    }
}
