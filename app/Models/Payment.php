<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'currency',
        'amount',
        'description',
        'payment_gateway_id',
        'gateway_reference',
        'transaction_reference',
        'transaction_status',
        'status',
        'method',
        'ip_address',
        'callback_url',
        'cancel_url',
        'checkout_url',
        'paid_at',
        'verified_at',
        'verified_by',
    ];

    /**
     * Name accessor for is paid state.
     */
    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->paid_at)
        );
    }

    /**
     * Name accessor for is verified state.
     */
    protected function isVerified(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->verified_at)
        );
    }

    /**
     * Search scope
     */
    public function scopeIsPaid(Builder $query, bool $isPaid): Builder
    {
        return $query->when($isPaid, function (Builder $query) {
            $query->whereNotNull('paid_at');
        }, function (Builder $query) {
            $query->whereNull('paid_at');
        });
    }

    /**
     * Search scope
     */
    public function scopeIsVerified(Builder $query, bool $isVerified): Builder
    {
        return $query->when($isVerified, function (Builder $query) {
            $query->whereNotNull('verified_at');
        }, function (Builder $query) {
            $query->whereNull('verified_at');
        });
    }

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('currency', 'like', "%{$term}%")
                ->orWhere('status', 'like', "%{$term}%")
                ->orWhere('method', 'like', "%{$term}%")
                ->orWhere('amount', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('gateway_reference', 'like', "%{$term}%")
                ->orWhere('transaction_reference', 'like', "%{$term}%")
                ->orWhere('transaction_status', 'like', "%{$term}%")
                ->orWhereHas('user', function ($query) use ($term) {
                    $query->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone_number', 'like', "%{$term}%");
                });
        });
    }

    /**
     * Get all the payables
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class, 'payment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }
}
