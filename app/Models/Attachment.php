<?php

namespace App\Models;

use App\Observers\AttachmentObserver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * @disregard
 */
#[ObservedBy([AttachmentObserver::class])]
class Attachment extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'path',
        'url',
        'type',
        'mime_type',
        'extension',
        'size',
        'storage',
        'attachable_id',
        'attachable_type',
    ];

    /**
     * Get the parent attachable model
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function url(): Attribute
    {
        return Attribute::make(
            get: function () {
                /**
                 * @disregard
                 */
                return Storage::disk($this->storage)->url($this->path);
            }
        );
    }
}
