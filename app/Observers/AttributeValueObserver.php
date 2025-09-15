<?php

namespace App\Observers;

use App\Models\AttributeValue;

class AttributeValueObserver
{
    /**
     * Handle the AttributeValue "created" event.
     */
    public function created(AttributeValue $attributeValue): void
    {
        //
    }

    /**
     * Handle the AttributeValue "updated" event.
     */
    public function updated(AttributeValue $attributeValue): void
    {
        //
    }

    /**
     * Handle the AttributeValue "restored" event.
     */
    public function saving(AttributeValue $attributeValue): void
    {
        if (blank($attributeValue->display_value)) {
            $attributeValue->display_value = $attributeValue->value;
        }
    }

    /**
     * Handle the AttributeValue "deleted" event.
     */
    public function deleted(AttributeValue $attributeValue): void {}

    /**
     * Handle the AttributeValue "restored" event.
     */
    public function restored(AttributeValue $attributeValue): void {}

    /**
     * Handle the AttributeValue "force deleted" event.
     */
    public function forceDeleted(AttributeValue $attributeValue): void
    {
        //
    }
}
