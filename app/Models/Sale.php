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
use Illuminate\Support\Str;

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
        'cashier_staff_id',
        'discount_id',
        'customer_user_id',
        'customer_name',
        'customer_email',
        'customer_phone_number',
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
            $builder->when(app()->bound('currentStoreId'), function ($builder) {
                $builder->whereHas('inventories', function (Builder $builder) {
                    $builder->where('store_id', app('currentStoreId'));
                });
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
     * Get the Sale
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'discount_id');
    }

    /**
     * Get the Customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the sales inventories associated with the sale.
     */
    public function saleInventories(): HasMany
    {
        return $this->hasMany(SaleInventory::class, 'sale_id');
    }

    /**
     * Get the inventories associated with the sale.
     */
    public function inventories(): BelongsToMany
    {
        return $this->belongsToMany(Inventory::class, SaleInventory::class, 'sale_id', 'inventory_id')
            ->withPivot(
                'quantity',
                'weight',
                'price_per_gram',
                'total_price',
                'daily_gold_price_id',
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
