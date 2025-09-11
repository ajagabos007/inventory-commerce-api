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
            'user_id' => null,
            'attachable_id' => $this->faker->uuid(),
            'attachable_type' => 'App\\Models\\Fake',
            'storage' => $this->faker->randomElement(['local', 'public']),
            'size' => $this->faker->word(),
            'extension' => $this->faker->fileExtension(),
            'mime_type' => $this->faker->mimeType(),
            'type' => $this->faker->file(),
            'path' => $this->faker->filePath(),
            'name' => 'Fake Image',
        ];
    }
}
