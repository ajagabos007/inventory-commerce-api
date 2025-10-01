<?php

namespace App\Models;

use App\Observers\StockTransferInventoryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[ObservedBy([StockTransferInventoryObserver::class])]
class StockTransferInventory extends Pivot
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inventory_id',
        'stock_transfer_id',
        'quantity',
    ];

    /**
     * Scope a query to only include staff for the current store.
     */
    public function scopeForRequestStore(Builder $builder): void
    {
        $builder->whereHas('inventory.productVariant', function ($builder) {
            $builder->where('store_id', request()->header('x-store'));
        });
    }

    /**
     * Get the inventory
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    /**
     * Get the inventory
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
}
