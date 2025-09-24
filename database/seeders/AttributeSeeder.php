<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'Color',
                'values' => ['Black', 'White', 'Red', 'Blue', 'Silver'],
            ],
            [
                'name' => 'Size',
                'values' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            ],
            [
                'name' => 'Storage',
                'values' => ['64GB', '128GB', '256GB', '512GB', '1TB'],
            ],
            [
                'name' => 'RAM',
                'values' => ['4GB', '8GB', '16GB', '32GB'],
            ],

            [
                'name' => 'Shade',
                'values' => ['Ivory', 'Beige', 'Sand', 'Honey', 'Caramel', 'Chestnut', 'Mocha'],
            ],
            [
                'name' => 'Brand',
                'values' => ['Apple', 'Samsung', 'Dell', 'HP', 'Lenovo', 'Sony', 'Huawei', 'Xiaomi'],
            ],
            [
                'name' => 'Model Year',
                'values' => ['2019', '2020', '2021', '2022', '2023', '2024'],
            ],
            [
                'name' => 'Processor',
                'values' => ['Intel i3', 'Intel i5', 'Intel i7', 'Intel i9', 'AMD Ryzen 5', 'AMD Ryzen 7', 'Apple M1', 'Apple M2'],
            ],
            [
                'name' => 'Graphics',
                'values' => ['Integrated', 'NVIDIA GTX 1650', 'NVIDIA RTX 3060', 'NVIDIA RTX 4070', 'AMD Radeon RX 6600'],
            ],
            [
                'name' => 'Battery',
                'values' => ['3000mAh', '4000mAh', '5000mAh', '6000mAh'],
            ],
            [
                'name' => 'Camera',
                'values' => ['8MP', '12MP', '48MP', '64MP', '108MP'],
            ],
            [
                'name' => 'Display',
                'values' => ['HD', 'Full HD', '2K', '4K UHD', 'OLED', 'AMOLED', 'Retina'],
            ],
            [
                'name' => 'Operating System',
                'values' => ['Android', 'iOS', 'Windows', 'macOS', 'Linux', 'ChromeOS'],
            ],
            [
                'name' => 'Connectivity',
                'values' => ['WiFi', '4G LTE', '5G', 'Bluetooth 5.0', 'NFC'],
            ],
            [
                'name' => 'Material',
                'values' => ['Plastic', 'Aluminium', 'Glass', 'Carbon Fiber'],
            ],
            [
                'name' => 'Weight',
                'values' => ['Lightweight', 'Standard', 'Heavy'],
            ],
            [
                'name' => 'Ports',
                'values' => ['USB-C', 'USB-A', 'HDMI', 'Thunderbolt', '3.5mm Jack'],
            ],
            [
                'name' => 'Features',
                'values' => ['Waterproof', 'Dustproof', 'Fingerprint Sensor', 'Face Unlock', 'Wireless Charging'],
            ],

            [
                'name' => 'Finish',
                'values' => ['Matte', 'Glossy', 'Satin', 'Velvet', 'Shimmer'],
            ],
            [
                'name' => 'Skin Type',
                'values' => ['Normal', 'Oily', 'Dry', 'Combination', 'Sensitive'],
            ],
            [
                'name' => 'Fragrance Note',
                'values' => ['Citrus', 'Floral', 'Woody', 'Oriental', 'Fresh'],
            ],
            [
                'name' => 'SPF',
                'values' => ['SPF 15', 'SPF 30', 'SPF 50'],
            ],
            [
                'name' => 'Volume',
                'values' => ['10ml', '30ml', '50ml', '100ml'],
            ],
            [
                'name' => 'Formulation',
                'values' => ['Liquid', 'Powder', 'Cream', 'Gel', 'Stick'],
            ],
            [
                'name' => 'Hair Type',
                'values' => ['Straight', 'Wavy', 'Curly', 'Coily'],
            ],
            [
                'name' => 'Coverage',
                'values' => ['Sheer', 'Medium', 'Full'],
            ],
        ];

        foreach ($attributes as $attr) {
            $attribute = Attribute::firstOrCreate([
                'name' => $attr['name'],
            ]);

            $attribute_values = [];

            foreach ($attr['values'] as $val) {
                $attribute_values[] = [
                    'id' => Str::uuid(),
                    'attribute_id' => $attribute->id,
                    'value' => $val,
                ];
            }

            if (count($attribute_values) == 0) {
                continue;
            }
            AttributeValue::upsert(
                $attribute_values,
                uniqueBy: ['attribute_id', 'value'],
                update: (new AttributeValue)->getFillable()
            );
        }
    }
}
