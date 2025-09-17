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
                'name' => 'Color Family',
                'values' => ['Red', 'Pink', 'Nude', 'Coral', 'Berry', 'Plum'],
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
