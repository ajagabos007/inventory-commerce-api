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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'address',
        'manager_staff_id',
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
     * Get the store manager
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_staff_id');
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
     * The products
     */
    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, Inventory::class)
            ->withPivot([
                'quantity',
                'status'
            ])->using(Inventory::class);
    }

    /**
     * Scope and get only head quaters
     */
    public function scopeWarehouses(Builder $query)
    {
        $query->where('is_warehouse', true);
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

    /**
     * Set the store manager to be a staff in the store
     */
    public function updateManagerAsStaff(): void
    {
        if (! is_null($this->manager) && $this->manager->store_id != $this->id) {
            $this->manager->store_id = $this->id;
            $this->manager->saveQuietly();
        }
    }
}
