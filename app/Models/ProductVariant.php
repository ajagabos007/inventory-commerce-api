<?php

namespace App\Models;

use App\Enums\InventoryStatus;
use App\Observers\ProductVariantObserver;
use App\Traits\FlexibleRouteBinding;
use App\Traits\HasAttachments;
use App\Traits\HasAttributeValues;
use App\Traits\ModelRequestLoader;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;
Use App\Traits\HasViews;

#[ObservedBy([ProductVariantObserver::class])]
class ProductVariant extends Model
{
    use FlexibleRouteBinding;
    use HasAttachments;
    use HasAttributeValues;

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;
    use Sluggable;
    use HasViews;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'slug',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
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
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            $builder->when(! app()->runningInConsole(), function ($builder) {
                $builder->whereHas('inventories', function ($query) {
                    $query->where('store_id', current_store()?->id);
                });
            });
        });
    }

    /**
     * Get the item's name
     */
    protected function quantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $metadata = $this->metada ?? [];
                if (array_key_exists('quantity', $metadata)) {
                    return $metadata['quantity'];
                }
                $metadata['quantity'] = $this->inventories()
//                    ->where('inventories.status', InventoryStatus::AVAILABLE)
                    ->sum('quantity');
                $this->metadata = $metadata;
                $this->saveQuietly();

                return $metadata['quantity'];
            }
        );
    }

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('sku', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('price', 'like', "%{$term}%")
                ->orWhere('compare_price', 'like', "%{$term}%")
                ->orWhere('cost_price', 'like', "%{$term}%")
                ->orWhereHas('product', function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%")
                        ->orWhere('display_price', 'like', "%{$term}%")
                        ->orWhere('display_compare_price', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Scope: Popular variants based on total sales - OPTIMIZED VERSION
     */
    public function scopePopular(Builder $query, $ordered = true): Builder
    {
        $salesSubquery = DB::table('sale_inventories')
            ->select('inventories.product_variant_id')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->join('inventories', 'inventories.id', '=', 'sale_inventories.inventory_id')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id');
            })
            ->groupBy('inventories.product_variant_id');

        $query->leftJoinSub($salesSubquery, 'popular_variants_sales', function ($join) {
            $join->on('product_variants.id', '=', 'popular_variants_sales.product_variant_id');
        })
            ->addSelect('product_variants.*')
            ->addSelect(DB::raw('COALESCE(popular_variants_sales.total_sold, 0) as popular_variants_total_sold'));

        if ($ordered) {
            $query->orderByDesc('popular_variants_total_sold');
        } else {
            $query->havingRaw('COALESCE(popular_variants_total_sold, 0) > 0');
        }

        return $query;
    }

    /**
     * Scope: Trending variants (recent sales)
     */
    public function scopeTrending(Builder $query, $days = 30): Builder
    {
        $startDate = now()->subDays($days)->toDateTimeString();

        $salesSubquery = DB::table('sale_inventories')
            ->select('inventories.product_variant_id')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->join('inventories', 'inventories.id', '=', 'sale_inventories.inventory_id')
            ->join('sales', function ($join) use ($startDate) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.created_at', '>=', $startDate);
            })
            ->groupBy('inventories.product_variant_id');

        return $query->leftJoinSub($salesSubquery, 'trending_variants_sales', function ($join) {
            $join->on('product_variants.id', '=', 'trending_variants_sales.product_variant_id');
        })
            ->addSelect('product_variants.*')
            ->addSelect(DB::raw('COALESCE(trending_variants_sales.total_sold, 0) as trending_variants_total_sold'))
            ->havingRaw('COALESCE(trending_variants_total_sold, 0) > 0')
            ->orderByDesc('trending_variants_total_sold');
    }

    /**
     * Scope: Variants that have at least one sale
     */
    public function scopeHasSales(Builder $query): Builder
    {
        return $query->whereHas('inventories.saleInventories', function ($q) {
            $q->whereHas('sale', function ($sq) {
                $sq->where('status', 'completed');
            });
        });
    }

    /**
     * Scope: Top selling variants (limited)
     */
    public function scopeTopSelling(Builder $query, $limit = 10): Builder
    {
        $salesSubquery = DB::table('sale_inventories')
            ->select('inventories.product_variant_id')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->join('inventories', 'inventories.id', '=', 'sale_inventories.inventory_id')
            ->join('sales', function ($join) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id');
            })
            ->groupBy('inventories.product_variant_id');

        return $query->leftJoinSub($salesSubquery, 'top_selling_variants_sales', function ($join) {
            $join->on('product_variants.id', '=', 'top_selling_variants_sales.product_variant_id');
        })
            ->addSelect('product_variants.*')
            ->addSelect(DB::raw('COALESCE(top_selling_variants_sales.total_sold, 0) as top_selling_variants_total_sold'))
            ->havingRaw('COALESCE(top_selling_variants_total_sold, 0) > 0')
            ->orderByDesc('top_selling_variants_total_sold')
            ->limit($limit);
    }

    /**
     * Scope: Popular from specific date
     */
    public function scopePopularFrom(Builder $query, $date): Builder
    {
        $salesSubquery = DB::table('sale_inventories')
            ->select('inventories.product_variant_id')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->join('inventories', 'inventories.id', '=', 'sale_inventories.inventory_id')
            ->join('sales', function ($join) use ($date) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.created_at', '>=', $date);
            })
            ->groupBy('inventories.product_variant_id');

        return $query->leftJoinSub($salesSubquery, 'popular_from_variants_sales', function ($join) {
            $join->on('product_variants.id', '=', 'popular_from_variants_sales.product_variant_id');
        })
            ->addSelect('product_variants.*')
            ->addSelect(DB::raw('COALESCE(popular_from_variants_sales.total_sold, 0) as popular_from_variants_total_sold'))
            ->havingRaw('COALESCE(popular_from_variants_total_sold, 0) > 0')
            ->orderByDesc('popular_from_variants_total_sold');
    }

    /**
     * Scope: Popular up to specific date
     */
    public function scopePopularTo(Builder $query, $date): Builder
    {
        $salesSubquery = DB::table('sale_inventories')
            ->select('inventories.product_variant_id')
            ->selectRaw('COALESCE(SUM(sale_inventories.quantity), 0) as total_sold')
            ->join('inventories', 'inventories.id', '=', 'sale_inventories.inventory_id')
            ->join('sales', function ($join) use ($date) {
                $join->on('sales.id', '=', 'sale_inventories.sale_id')
                    ->where('sales.created_at', '<=', $date);
            })
            ->groupBy('inventories.product_variant_id');

        return $query->leftJoinSub($salesSubquery, 'popular_to_variants_sales', function ($join) {
            $join->on('product_variants.id', '=', 'popular_to_variants_sales.product_variant_id');
        })
            ->addSelect('product_variants.*')
            ->addSelect(DB::raw('COALESCE(popular_to_variants_sales.total_sold, 0) as popular_to_variants_total_sold'))
            ->havingRaw('COALESCE(popular_to_variants_total_sold, 0) > 0')
            ->orderByDesc('popular_to_variants_total_sold');
    }

    /**
     * Get the item's name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function ($name) {
                if (blank($name)) {
                    return $this->product->name;
                }

                return $name;
            }
        );

    }

    /**
     * Get the available quantity
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::make(
            get: function () {
                $metadata = $this->metadata ?? [];

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
     * Get the product of the variant
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The inventories
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_variant_id');
    }

    /**
     * Get the stores
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, Inventory::class)
            ->withPivot([
                'quantity',
                'status',
            ])->using(Inventory::class);
    }

    /**
     * Generate item barcode
     */
    public function generateBarcode(): ?string
    {
        if (! blank($this->barcode) && is_string($this->barcode)) {
            if (Str::startsWith($this->barcode, 'data:image/jpeg;base64,')) {
                return $this->barcode;
            }
        }

        if (blank($this->sku)) {
            return null;
        }

        $this->sku = ProductVariant::generateSKU();

        /**
         * @see https://github.com/milon/barcode
         */
        return 'data:image/png;base64,'.(new DNS1D)->getBarcodePNG($this->sku, 'c128', $w = 1, $h = 33, [0, 0, 0], true);
    }

    /**
     * Generate a unique invoice number.
     *
     * @throws \RuntimeException
     */
    public static function generateSKU(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $sku = strtoupper(Str::random(12));
            $exists = self::where('sku', $sku)->exists();
            $attempt++;
            sleep(1); // Sleep for a short duration to avoid rapid retries;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            throw new \RuntimeException("Unable to generate a unique product variant sku after {$attempt} attempts.");
        }

        return $sku;
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
