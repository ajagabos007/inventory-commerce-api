<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Observers\ProductObserver;
use App\Traits\HasAttachments;
use App\Traits\HasAttributeValues;
use App\Traits\HasCategories;
use App\Traits\ModelRequestLoader;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasAttachments;
    use HasAttributeValues;
    use HasCategories;

    /** @use HasFactory<ProductFactory> */
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
        'description',
        'short_description',
        'display_price',
        'display_compare_price',
        'is_serialized',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['available_quantity'];

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
                $builder->whereHas('variants.inventories', function ($query) {
                    $query->where('store_id', current_store()?->id);
                });
            });
        });
    }

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
     * Get the available quantity
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $metadata = $this->metada ?? [];

                $availableQuantity = data_get($metadata, 'available_quantity', 0);

                if (current_store()) {
                    $storeId = current_store()->id;
                    $availableQuantity = data_get($metadata, 'stores.'.$storeId.'.available_quantity');
                }

                return $availableQuantity;
            }
        );
    }

    /**
     * Get the item's name
     */
    protected function displayPrice(): Attribute
    {
        return Attribute::make(
            get: function ($display_price) {

                if (! blank($display_price)) {
                    return $display_price;
                }
                $min_price = $this->variants()->min('price');
                $max_price = $this->variants()->max('price');

                return $min_price == $max_price ? $min_price : $min_price.'-'.$max_price;
            }
        );
    }

    /**
     * Get the item's price
     */
    protected function displayComparePrice(): Attribute
    {
        return Attribute::make(
            get: function ($display_compare_price) {

                if (! blank($display_compare_price)) {
                    return $display_compare_price;
                }

                $min_compare_price = $this->variants()->min('compare_price');
                $max_compare_price = $this->variants()->max('compare_price');

                return $min_compare_price == $max_compare_price ? $min_compare_price : $min_compare_price.'-'.$max_compare_price;
            }
        );
    }

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('slug', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")
                ->orWhere('display_price', 'like', "%{$term}%")
                ->orWhere('display_compare_price', 'like', "%{$term}%");
        });
    }

    /**
     * Scope inventories low in stock
     */
    public function scopeLowStock($query, $threshold = 5)
    {
        $threshold = filter_var($threshold, FILTER_VALIDATE_INT);
        $threshold = $threshold === false ? 5 : $threshold;

        return $query->whereHas('variants', function ($query) use ($threshold) {
            $query->where('quantity', '<=', $threshold)
                ->where('quantity', '>', 0);
        });
    }

    /**
     * Scope inventories out of stock
     */
    public function scopeOutOfStock(Builder $query, $out_of_stock = true): Builder
    {
        $out_of_stock = filter_var($out_of_stock, FILTER_VALIDATE_BOOLEAN);

        $query->when($out_of_stock, function ($query) {
            return $query->whereHas('variants', function ($query) {
                $query->where('quantity', '<=', 0);
            });

        }, function ($query) {
            return $query->whereHas('variants', function ($query) {
                $query->where('quantity', '>', 0);
            });
        });

        return $query;
    }

    /**
     * The products
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get all the inventories for the productions.
     */
    public function inventories(): HasManyThrough
    {
        return $this->hasManyThrough(Inventory::class, ProductVariant::class);

    }

    /**
     * The products
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, Inventory::class)
            ->withPivot('quantity')->using(Inventory::class);
    }

    public function updateDisplayPrices(): void
    {
        $min_price = $this->variants()->min('price');
        $max_price = $this->variants()->max('price');
        $this->display_price = $min_price == $max_price ? $min_price : $min_price.'-'.$max_price;

        $min_compare_price = $this->variants()->min('compare_price');
        $max_compare_price = $this->variants()->max('compare_price');
        $this->display_compare_price = $min_compare_price == $max_compare_price ? $min_compare_price : $min_compare_price.'-'.$max_compare_price;

        $this->save();
    }

    public function updateAvailableQuantity(): void
    {
        $availableQuantity = $this->inventories()
            ->where('inventories.status', InventoryStatus::AVAILABLE)
            ->sum('quantity');
        $medata = $this->metadata ?? [];

        if (current_store()) {
            $medata['stores'][current_store()->id]['id'] = current_store()->id;
            $medata['stores'][current_store()->id]['available_quantity'] = $availableQuantity;
        } else {
            $medata['available_quantity'] = $availableQuantity;
        }

        $this->metadata = $medata;
        $this->saveQuietly();
    }
}
