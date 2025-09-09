<?php

namespace App\Traits;

use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAttributeValues
{
    /**
     * Get all of the tags for the post.
     */
    public function attributeValues(): MorphToMany
    {
        return $this->morphToMany(AttributeValue::class, 'attributable');
    }
}
