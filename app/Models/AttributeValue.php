<?php

namespace App\Models;

use App\Observers\AttributeValueObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\AttributeValueFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[ObservedBy([AttributeValueObserver::class])]
class AttributeValue extends Model
{
    /** @use HasFactory<AttributeValueFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attribute_id',
        'value',
        'display_value',
        'sort_order',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'attributable');
    }

    public function productVariants(): MorphToMany
    {
        return $this->morphedByMany(ProductVariant::class, 'attributable');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('value', 'like', "%{$term}%")
                ->orWhere('display_value', 'like', "%{$term}%")
                ->orWhereHas('attribute', function ($query) use ($term) {
                    $query->search($term);
                });
        });
    }
}
