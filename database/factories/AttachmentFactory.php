<?php

namespace Database\Factories;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'deleted_at' => $this->faker->word(),
            'updated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'user_id' => $this->faker->word(),
            'attachable_id' => $this->faker->word(),
            'attachable_type' => $this->faker->word(),
            'storage' => $this->faker->word(),
            'size' => $this->faker->word(),
            'extension' => $this->faker->word(),
            'mime_type' => $this->faker->word(),
            'type' => $this->faker->word(),
            'path' => $this->faker->word(),
            'name' => $this->faker->name(),
        ];
    }
}
