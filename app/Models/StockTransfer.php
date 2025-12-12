<?php

namespace App\Models;

use App\Observers\StockTransferObserver;
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

#[ObservedBy([StockTransferObserver::class])]
class StockTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\StockTransferFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reference_no',
        'sender_id',
        'receiver_id',
        'driver_name',
        'driver_phone_number',
        'comment',
        'status',
        'from_store_id',
        'to_store_id',
        'accepted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'metadata' => 'json',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
//            $builder->when(! app()->runningInConsole(), function ($builder) {
//                $builder->where('to_store_id', current_store()?->id)
//                    ->orWhere('from_store_id', current_store()?->id);
//            });
        });
    }

    public function scopeCurrentStore(Builder $builder): Builder
    {
        return   $builder->where('to_store_id', current_store()?->id)
            ->orWhere('from_store_id', current_store()?->id);
    }

    /**
     * Get the from store
     */
    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * Get the to store
     */
    public function toStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /**
     * Get the sender
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the receiver
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get the stock transfer inventories.
     */
    public function stockTransferInventories(): HasMany
    {
        return $this->hasMany(StockTransferInventory::class, 'stock_transfer_id');
    }

    /**
     * The inventories
     */
    public function inventories(): BelongsToMany
    {
        return $this->belongsToMany(Inventory::class, StockTransferInventory::class, 'stock_transfer_id', 'inventory_id')
            ->withPivot('id', 'quantity')->using(StockTransferInventory::class)
            ->withoutGlobalScope('store');
    }

    /**
     * Scope a query to only include popular users.
     */
    public function scopeIncoming(Builder $query): void
    {
        $query->where(function ($query) {
            $query->where('receiver_id', auth()->id())
                ->orWhere('to_store_id', auth()->user()?->staff?->store_id);
        })
            ->where('status', 'dispatched');
    }

    /**
     * Scope a query to only include outgoing stock transfers.
     */
    public function scopeOutgoing(Builder $query): void
    {
        $query->where(function ($query) {
            $query->where('sender_id', auth()->id())
                ->orWhere('from_store_id', auth()->user()?->staff?->store_id);
        })
            ->where('status', 'dispatched');
    }

    /**
     * Generate a unique stock transfer reference number.
     *
     * @throws \RuntimeException
     */
    public static function generateReferenceNo(): string
    {
        $maxAttempts = 100;
        $attempt = 0;

        do {
            if (++$attempt > $maxAttempts) {
                throw new \RuntimeException("Failed to generate a unique stock transfer reference number after {$maxAttempts} attempts.");
            }

            $reference_no = strtoupper(Str::random(10));
        } while (self::where('reference_no', $reference_no)->exists());

        return $reference_no;
    }
}
