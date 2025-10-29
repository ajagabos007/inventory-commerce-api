<?php

namespace App\Models;

use App\Observers\OrderObserver;
use App\Traits\HasPayments;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy([OrderObserver::class])]

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasPayments;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'discount_id',
        'reference',
        'full_name',
        'phone_number',
        'email',
        'delivery_method',
        'delivery_address',
        'pickup_address',
        'status',
        'payment_method',
        'total_price',
        'subtotal_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_address' => 'array',
            'pickup_address' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the coupon
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'coupon_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function genReference(): string
    {
        do {
            $ref =  'ORD-' . Str::upper(Str::random(8));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }
}
