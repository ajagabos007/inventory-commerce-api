<?php

namespace App\Models;

use App\Observers\SaleObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Milon\Barcode\DNS1D;

#[ObservedBy([SaleObserver::class])]
class Sale extends Model
{
    /** @use HasFactory<\Database\Factories\SaleFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_number',
        'barcode',
        'cashier_staff_id',
        'discount_id',
        'discount_amount',
        'buyerable_id',
        'buyerable_type',
        'tax',
        'payment_method',
        'subtotal_price',
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
                $builder->whereDoesntHave('inventories', function (Builder $builder) {
                    $builder->where('store_id', '<>', current_store()?->id);
                });
            });
        });
    }

    public function scopeCurrentStore(Builder $builder): Builder
    {
        return  $builder->whereDoesntHave('inventories', function (Builder $builder) {
            $builder->where('store_id', '<>', current_store()?->id);
        });
    }
    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($query) use ($term) {
            $query->where('invoice_number', 'like', "%{$term}%")
                ->orWhere('subtotal_price', 'like', "%{$term}%")
                ->orWhere('total_price', 'like', "%{$term}%")
                ->orWhere('payment_method', 'like', "%{$term}%")
                ->orWhere('tax', 'like', "%{$term}%")
                ->orWhere('channel', 'like', "%{$term}%")
                ->orWhereHas('buyerable', function ($buyerQuery) use ($term) {
                    $model = $buyerQuery->getModel();

                    return match (get_class($model)) {
                        \App\Models\Customer::class => $buyerQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone_number', 'like', "%{$term}%")
                            ->orWhere('country', 'like', "%{$term}%")
                            ->orWhere('city', 'like', "%{$term}%"),

                        \App\Models\User::class => $buyerQuery->where('first_name', 'like', "%{$term}%")
                            ->orWhere('middle_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('phone_number', 'like', "%{$term}%"),

                        default => $buyerQuery,
                    };
                });
        });
    }

    /**
     * Get the Cashier
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'cashier_staff_id');
    }

    /**
     * Get the discount
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'discount_id');
    }

    /**
     * Get the buyerable
     */
    public function buyerable(): MorphTo
    {
        return $this->morphTo('buyerable');
    }

    /**
     * Get the sales inventories associated with the sale.
     */
    public function saleInventories(): HasMany
    {
        return $this->hasMany(SaleInventory::class, 'sale_id', 'id');
    }

    /**
     * Get the inventories associated with the sale.
     */
    public function inventories(): BelongsToMany
    {
        return $this->belongsToMany(Inventory::class, SaleInventory::class, 'sale_id', 'inventory_id')
            ->withPivot(
                'quantity',
                'price',
                'total_price',
                'metadata'
            )
            ->using(SaleInventory::class)
            ->withTimestamps();
    }

    public function scopeVisibleToUser($query, $user = null)
    {
        $user ??= auth()->user();

        return $query->whereHas('inventories', function ($sub) use ($user) {
            $sub->where('store_id', $user?->staff?->store_id);
        })
            ->orWhere('customer_user_id', $user?->id);
    }

    /**
     * Initialize item sku
     */
    public static function generateInvoiceNumber(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            if (++$attempt > $maxAttempts) {
                throw new \RuntimeException("Unable to generate a unique invoice number after {$attempt} attempts.");
            }
            $invoice_number = strtoupper(Str::random(10));

        } while (self::where('invoice_number', $invoice_number)->exists());

        return $invoice_number;
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

        if (blank($this->invoice_number)) {
            return null;
        }

        $this->invoice_number = Sale::generateInvoiceNumber();

        /**
         * @see https://github.com/milon/barcode
         */
        return 'data:image/png;base64,'.(new DNS1D)->getBarcodePNG($this->invoice_number, 'c128', $w = 1, $h = 33, [0, 0, 0], true);
    }

    /**
     * Calculate and update the subtotal and total prices for the sale.
     *
     * This method recalculates the subtotal price based on the associated sale inventories,
     * applies tax, and adjusts the total price if a discount is present in the metadata.
     */
    public function updatePricing(): self
    {
        if ($this->saleInventories()->count() > 0) {
            $this->subtotal_price = $this->saleInventories()->sum('total_price');
            $this->total_price = $this->subtotal_price + ($this->tax ?? 0);

            $metadata = $this->metadata ?? [];

            if (isset($metadata['discount']) && $metadata['discount'] instanceof Discount) {
                $discount = $metadata['discount'];
                $this->total_price -= $this->subtotal_price * ($discount->percentage / 100);
            }
        } else {
            $this->subtotal_price = 0;
            $this->total_price = 0;
        }

        $this->save();

        return $this;
    }
}
