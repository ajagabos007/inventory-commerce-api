<?php

namespace App\Models;

use App\Enums\OrderStatus;
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
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('order_status')
            ->setDescriptionForEvent(function () {
                $original = $this->getOriginal('status');   // old status
                $current  = $this->status?->value;          // new status

                $from = $original
                    ? OrderStatus::from($original)->description()
                    : 'N/A';

                $to = $current
                    ? OrderStatus::from($current)->description()
                    : 'N/A';

                return "Order status changed from '{$from}' to '{$to}'";
            });
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

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
              $query->whereAny([
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
              ] ,'LIKE', "%{$term}%");
        });
    }
}
