<?php

namespace App\Traits;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCategories
{
    /**
     * Get the category
     */
    public function category(): MorphMany
    {
        return $this->morphOne(Category::class, 'categorizable');
    }

    /**
     * Get the categories
     */
    public function categories(): MorphMany
    {
        return $this->morphMany(Category::class, 'categorizable');
    }
}
