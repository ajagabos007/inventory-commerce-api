<?php

namespace App\Traits;
use App\Models\Store;
use App\Models\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToStore
{
    /**
     * Get the model's tenant
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Add sobal scope
     *
     * @return void
     */
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function ($model) {

            if(app()->runningInConsole()){
                return;
            }

            if (! $model->getAttribute('store_id') && ! $model->relationLoaded('store')) {
                $store_id = current_store()?->id;
                if(!blank($store_id)) {
                    $model->setAttribute('store_id', $store_id);
                }
            }
        });
    }
}
