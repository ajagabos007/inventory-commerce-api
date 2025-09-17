<?php

namespace App\Models;

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
        return $query->where('slug', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orWhere('short_description', 'like', "%{$term}%")
            ->orWhere('display_price', 'like', "%{$term}%")
            ->orWhere('display_compare_price', 'like', "%{$term}%");
    }

    /**
     * The products
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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
}
