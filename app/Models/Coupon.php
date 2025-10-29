<?php

namespace App\Models;

use App\Observers\CouponObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CouponObserver::class])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_amount',
        'maximum_discount_amount',
        'usage_limit',
        'usage_limit_per_user',
        'usage_count',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_count' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot(['order_id', 'discount_amount', 'used_at'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // ============================================
    // QUERY SCOPES
    // ============================================

    public function scopeActive(Builder $query, bool $isActive = true): Builder
    {
        return $query->where('is_active', $isActive);
    }

    public function scopeValid(Builder $query, bool $isValid = true): Builder
    {
        return $query->when($isValid, function ($q) {
            $q->where(function ($query) {
                $query->where('valid_from', '<=', now())
                    ->orWhereNull('valid_from');
            })->where(function ($query) {
                $query->where('valid_until', '>=', now())
                    ->orWhereNull('valid_until');
            });
        }, function ($q) {
            $q->where(function ($query) {
                $query->where('valid_from', '>', now())
                    ->orWhere('valid_until', '<', now());
            });
        });
    }

    public function scopeAvailable(Builder $query, bool $isAvailable = true): Builder
    {
        return $query->when($isAvailable, function ($q) {
            // Available: active, valid, and under usage limit (if any)
            $q->active()
                ->valid()
                ->where(function ($query) {
                    $query->whereNull('usage_limit')
                        ->orWhereColumn('usage_count', '<', 'usage_limit');
                });
        }, function ($q) {
            // Unavailable: inactive, expired, or usage limit reached
            $q->where(function ($query) {
                $query->where(function ($query) {
                    $query->valid(false);
                })
                    ->orWhere(function ($query) {
                        $query->active(false);
                    })
                    ->orWhere(function ($query) {
                        $query->whereNotNull('usage_limit')
                            ->whereColumn('usage_count', '>=', 'usage_limit');
                    });
            });
        });
    }

    public function scopeExpired(Builder $query, bool $isExpired = true): Builder
    {
        return $query->valid(! $isExpired);
    }

    // ============================================
    // VALIDATION METHODS
    // ============================================

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (now()->lt($this->valid_from) || now()->gt($this->valid_until)) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function canBeUsedByUser(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        if (! $this->isValid()) {
            return false;
        }

        $userUsageCount = $this->users()
            ->where('user_id', $userId)
            ->count();

        return $userUsageCount < $this->usage_limit_per_user;
    }

    public function meetsMinimumAmount(float $orderAmount): bool
    {
        if (! $this->minimum_order_amount) {
            return true;
        }

        return $orderAmount >= $this->minimum_order_amount;
    }

    // ============================================
    // DISCOUNT CALCULATION
    // ============================================

    public function calculateDiscount(float $orderAmount): float
    {
        if (! $this->meetsMinimumAmount($orderAmount)) {
            return 0;
        }

        $discount = 0;

        if ($this->type === 'percentage') {
            $discount = ($orderAmount * $this->value) / 100;
        } elseif ($this->type === 'fixed') {
            $discount = $this->value;
        }

        // Apply maximum discount cap if set
        if ($this->maximum_discount_amount) {
            $discount = min($discount, $this->maximum_discount_amount);
        }

        // Ensure discount doesn't exceed order amount
        $discount = min($discount, $orderAmount);

        return round($discount, 2);
    }

    // ============================================
    // USAGE TRACKING
    // ============================================

    public function markAsUsed(int $userId, int $orderId, float $discountAmount): void
    {
        $this->users()->attach($userId, [
            'order_id' => $orderId,
            'discount_amount' => $discountAmount,
            'used_at' => now(),
        ]);

        $this->increment('usage_count');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getFormattedValueAttribute(): string
    {
        if ($this->type === 'percentage') {
            return $this->value.'%';
        }

        return '$'.number_format($this->value, 2);
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if (! $this->usage_limit) {
            return null;
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }
}
