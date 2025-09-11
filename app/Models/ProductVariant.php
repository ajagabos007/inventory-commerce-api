<?php

namespace App\Models;

use App\Observers\ProductVariantObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

#[ObservedBy([ProductVariantObserver::class])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
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
        'sku',
        'price',
        'compare_price',
        'cost_price',
    ];

    /**
     * Get the item's name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function ($name) {
                if (! blank($name)) {
                    return $name;
                }

                return $this->product->name;
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
            $sku = strtoupper(Str::random(10));
            $exists = self::where('sku', $sku)->exists();
            $attempt++;
            sleep(1); // Sleep for a short duration to avoid rapid retries;
        } while ($exists && $attempt < $maxAttempts);

        if ($exists) {
            throw new \RuntimeException("Unable to generate a unique product variant sku after {$attempt} attempts.");
        }

        return $sku;
    }
}
