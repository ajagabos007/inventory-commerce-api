<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Inventory extends Pivot
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * the table name
     */
    public $table = 'inventory';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'store_id',
        'quantity',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            $builder->when(app()->bound('currentStoreId'), function ($builder) {
                $builder->where('store_id', app('currentStoreId'));
            });
        });
    }

    /**
     * Get the parent type
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Get the parent type
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** `
     * Get the parent type
     */
    public function saleInventories(): HasMany
    {
        return $this->hasMany(SaleInventory::class, 'inventory_id');
    }

    /**
     * The sales that belong to the role.
     */
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class, SaleInventory::class, 'inventory_id', 'sale_id')
            ->withPivot(
                'quantity',
                'weight',
                'price_per_gram',
                'total_price',
                'daily_gold_price_id',
                'metadata',
            );
    }

    /**
     * Scope inventories low in stock
     */
    public function scopeLowStock($query, $threshold = 5)
    {
        $threshold = filter_var($threshold, FILTER_VALIDATE_INT);
        $threshold = $threshold === false ? 5 : $threshold;

        return $query->where('quantity', '<=', $threshold)
            ->where('quantity', '>', 0);
    }

    /**
     * Scope inventories low in stock
     */
    public function scopeBelongingToCurrentStaff($query, $threshold = 5)
    {
        return $query->whereHas('item', function ($query) {
            $user = auth()->user() ?? auth('sanctum')->user();
            $query->where('store_id', $user->staff?->store_id);
        });

    }

    /**
     * Scope inventories out of stock
     */
    public function scopeOutOfStock($query, $out_of_stock = true)
    {
        $out_of_stock = filter_var($out_of_stock, FILTER_VALIDATE_BOOLEAN);

        $query->when($out_of_stock, function ($query) {
            return $query->where('quantity', '<=', 0);
        }, function ($query) {
            return $query->where('quantity', '>', 0);
        });

        return $query;
    }

    /**
     * is admin accessor
     */
    protected function subTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->inventories->sum('total_price'),
        );
    }

    /**
     * Increment the quantity of the inventory.
     */
    public function incrementQuantity(int $amount): bool
    {
        $this->quantity += $amount;

        return $this->save();
    }

    /**
     * Decrement the quantity of the inventory.
     *
     * @return self
     */
    public function decrementQuantity(int $quanity): bool
    {
        if ($this->quantity < $quanity) {
            return false; // Not enough quantity to decrement
        }

        $this->quantity -= $quanity;
        if ($this->quantity < 0) {
            $this->qunatity = 0;
        }

        return $this->save();
    }
}
