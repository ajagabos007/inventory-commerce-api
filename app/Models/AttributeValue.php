<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class AttributeValue extends Model
{
    /** @use HasFactory<\Database\Factories\AttributeValueFactory> */
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attribute_id',
        'value',
        'sort_order',
    ];

    public function attribute():BelongsTo
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
}
