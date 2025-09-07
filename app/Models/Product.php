<?php

namespace App\Models;

use App\Observers\ProductObserver;
use App\Traits\HasAttachments;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasAttachments;

    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'weight',
        'category_id',
        'type_id',
        'colour_id',
        'material',
        'barcode',
        'price',
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['image'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function dailyGoldPrices(): HasMany
    {
        return $this->hasMany(DailyGoldPrice::class, 'category_id', 'category_id');
    }

    /**
     * Get the item's name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function () {
                $name = $this->attributes['name'] ?? '';

                if ($this->category) {
                    $name .= (blank($name) ? '' : ' ').$this->category->name;
                }
                if ($this->type) {
                    $name .= (blank($name) ? '' : ' ').$this->type->name;
                }
                if ($this->colour) {
                    $name .= (blank($name) ? '' : ' ').$this->colour->name;
                }
                if (! blank($this->material)) {
                    $name .= (blank($name) ? '' : ' ').'('.$this->material.')';
                }

                return $name;
            }

        );

    }

    /**
     * Get the item's price
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match (strtolower($this->material)) {
                    'gold' => ($this->dailyGoldPrices()->today()->first()?->price_per_gram ?? 0) * $this->weight ,
                    default => $this->price ?? 0
                };
            }

        );

    }

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the type
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * Get the type
     */
    public function colour(): BelongsTo
    {
        return $this->belongsTo(Colour::class, 'colour_id');
    }

    /**
     * Get the model's attachment.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function image(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('type', 'image');
    }

    /**
     * Get the models's images.
     */
    public function images(): MorphMany
    {
        return $this->attachments()->where('type', 'image');
    }

    /**
     * The products
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
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
