<?php

namespace App\Models;

use App\Observers\SaleInventoryObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([SaleInventoryObserver::class])]
class SaleInventory extends Model
{
    /** @use HasFactory<\Database\Factories\SaleInventoryFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sale_inventories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inventory_id',
        'sale_id',
        'quantity',
        'price',
        'total_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'json',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            $builder->when(! app()->runningInConsole(), function ($builder) {
                $builder->whereDoesntHave('inventory', function (Builder $builder) {
                    $builder->where('store_id', '<>', current_store()->id);
                });
            });
        });
    }

    /**
     * Get the sale
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Get the inventory
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
