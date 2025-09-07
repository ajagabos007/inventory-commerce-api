<?php

namespace Database\Seeders;

use App\Models\Type;
use Illuminate\Database\Seeder;

class TypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            'Chain',
            'Hand chain',
            'Pendant',
            'Set',
            'Complete Set',
            'Bangle',
            'Chord',
            'Nose Ring',
            'Ring',
            'Necklace',
            'Watch',
        ];

        $types = [];

        foreach ($data as $dt) {
            $types[] = Type::factory()->make([
                'name' => $dt,
            ])->toArray();
        }

        Type::upsert(
            $types,
            uniqueBy: ['name'],
            update: (new Type)->getFillable()
        );

        /**
         * Create Ring sub types
         */
        $ring_type = Type::where('name', 'ring')->first();

        if (! is_null($ring_type)) {
            $ring_sub_types = [];
            $sub_types = [
                'Fashion', 'Engagement', 'One Band',
                'Two Band', 'There Band',
            ];

            foreach ($sub_types as $key => $sub_type) {

                $ring_sub_types[] = Type::factory()->make([
                    'name' => $sub_type,
                    'parent_type_id' => $ring_type->id,
                ])->toArray();
            }

            Type::upsert(
                $ring_sub_types,
                uniqueBy: ['name'],
                update: (new Type)->getFillable()
            );

        }
    }
}
