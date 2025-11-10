<?php

namespace App\Models;

use App\Observers\StoreObserver;
use App\Traits\ModelRequestLoader;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([StoreObserver::class])]
class Store extends Model
{
    /** @use HasFactory<\Database\Factories\StoreFactory> */
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
        'email',
        'phone_number',
        'country',
        'city',
        'address',
        'is_warehouse',
    ];

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
     * Get the store staff
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'store_id');
    }

    /**
     * The inventories
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'store_id');
    }

    /**
     * The product variants
     */
    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, Inventory::class)
            ->withPivot([
                'quantity',
                'status',
            ])->using(Inventory::class);
    }

    /**
     * Scope and get only warehouses
     */
    public function scopeWarehouses(Builder $query): Builder
    {
        return $query->where('is_warehouse', true);
    }

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('name', 'like', "%{$term}%")
            ->orWhere('slug', 'like', "%{$term}%")
            ->orWhere('country', 'like', "%{$term}%")
            ->orWhere('city', 'like', "%{$term}%")
            ->orWhere('address', 'like', "%{$term}%")
            ->orWhere('phone_number', 'like', "%{$term}%");
    }

    /**
     * Mark the current store has headquarter
     *
     * @return void;
     */
    public function markAsWarehouse(): void
    {
        Store::where('id', '<>', $this->id)
            ->where('is_warehouse', true)
            ->update([
                'is_warehouse' => false,
            ]);

        if ($this->is_warehouse == false) {
            $this->is_warehouse;
            $this->saveQuietly();
        }
    }
}
