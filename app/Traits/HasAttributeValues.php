<?php

namespace App\Traits;

use App\Models\Attributable;
use App\Models\AttributeValue;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAttributeValues
{
    /**
     * Get all the attribute values of the model.
     */
    public function attributeValues(): MorphToMany
    {
        return $this->morphToMany(AttributeValue::class, 'attributable')
            ->using(Attributable::class);
    }
}
