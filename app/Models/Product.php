<?php

namespace App\Models;

use App\Observers\ProductObserver;
use App\Traits\HasAttachments;
use App\Traits\ModelRequestLoader;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasAttachments;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

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
     * Get the item's name
     */
    protected function dipslayPrice(): Attribute
    {
        return Attribute::make(
            get: function () {}

        );

    }

    /**
     * Get the item's price
     */
    protected function displayComparePrice(): Attribute
    {
        return Attribute::make(
            get: function () {}

        );

    }

    /**
     * Get the type
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
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

    /**
     * Generate item barcode
     *
     * @return string|null
     */
    public function generateBarcode()
    {

        if ($this->barcode != null && ! empty($this->barcode)) {
            // If barcode already exists, return it
            if (Str::startsWith($this->barcode, 'data:image/jpeg;base64,')) {
                return $this->barcode;
            }
        }

        if (! empty($this->sku)) {
            return;
        }

        $this->sku = Product::generateSKU();

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
            $sku = strtoupper(Str::random(6));
            $exists = self::where('sku', $sku)->exists();
            $attempt++;
            sleep(1); // Sleep for a short duration to avoid rapid retries;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            throw new \RuntimeException("Unable to generate a unique sku after {$attempt} attempts.");
        }

        return $sku;
    }
}
