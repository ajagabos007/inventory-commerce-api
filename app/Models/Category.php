<?php

namespace App\Models;

use App\Observers\CategoryObserver;
use App\Traits\ModelRequestLoader;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([CategoryObserver::class])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;
    use Sluggable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url'];

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Get the parent categorizable model (product ...).
     */
    public function categorizable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the parent category
     */
    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the sub categories
     */
    public function subCategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Search Category
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('slug', 'like', "%{$term}%");
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function imageUrl(): Attribute
    {
        return Attribute::make(function (): string {

            if (is_null($this->image_path) || ! Storage::disk($this->ImageDisk())->exists($this->image_path)) {
                return $this->defaultImageUrl();
            }

            return Storage::disk($this->ImageDisk())->url($this->image_path);
        });
    }

    /**
     * Update the user's profile photo.
     *
     * @param  string  $storagePath
     * @return void
     */
    public function updateImage(UploadedFile|string $image, $storagePath = 'images')
    {
        if (is_string($image)) {
            $parts = explode(';base64,', $image);
            $file_data = base64_decode($parts[1]);
            $mime_type = str_replace('data:', '', $parts[0]);

            $tmp_file_path = tempnam(sys_get_temp_dir(), 'base64_');
            file_put_contents($tmp_file_path, $file_data);

            $extension = explode('/', $mime_type)[1];
            $file_name = uniqid().'.'.$extension;

            $image = new UploadedFile($tmp_file_path, $file_name, $mime_type, null, true);
        }
        tap($this->image_path, function ($previous) use ($image, $storagePath) {

            $storagePath = str_contains($storagePath, 'categories') ? $storagePath : 'categories/'.$storagePath;

            $this->forceFill([
                'image_path' => $image->storePublicly(
                    $storagePath, ['disk' => $this->ImageDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->ImageDisk())->delete($previous);
            }

        });
    }

    /**
     * Delete the category image
     */
    public function deleteImage(): void
    {
        if (is_null($this->image_path)) {
            return;
        }

        Storage::disk($this->ImageDisk())->delete($this->image_path);

        $this->forceFill([
            'image_path' => null,
        ])->save();
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     */
    protected function defaultImageUrl(): string
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        $colors = ['ff6b6b', '4ecdc4', '45b7d1', 'f9ca24', 'f0932b', 'eb4d4b', '6c5ce7'];
        $color = $colors[array_rand($colors)];
        $name = urlencode($this->name);

        return "https://placehold.co/300x300/{$color}/white?text={$name}";
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function ImageDisk(): string
    {
        $disk = Storage::disk()->getConfig()['driver'];

        return $disk == 'local' ? 'public' : $disk;
    }
}
