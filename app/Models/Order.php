<?php

namespace App\Models;

use App\Observers\OrderObserver;
use App\Traits\HasPayments;
use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

#[ObservedBy([OrderObserver::class])]

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    use HasPayments;
    use HasUuids;
    use ModelRequestLoader;
    use Scopeable;
//    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'discount_id',
        'discount_amount',
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

    /**
     * Status of order
     * Ongoing: an order waiting payment
     * New: when payment is made and verified
     * Processing: once an order being review
     * Dispatched : sent out for delivery
     * Delivered : delivered and received by the customer
     *
     * Order_status
     * id
     * user_id
     * from_status
     * to_status
     * time_stamp
     *
     */

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status']);
        // Chain fluent methods for configuration options
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
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
            $ref = 'ORD-'.Str::upper(Str::random(8));
        } while (static::where('reference', $ref)->exists());

        return $ref;
    }
}
