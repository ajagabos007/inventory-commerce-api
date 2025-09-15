<?php

namespace App\Traits;

use App\Models\Categorizable;
use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasCategories
{
    /**
     * Get the categories
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable')
            ->using(Categorizable::class);
    }

    /**
     * Handle attributable delete event
     */
    public static function bootHasCategories(): void
    {
        static::deleted(function ($model) {
            $model->categories()->detach();
        });
    }
}
