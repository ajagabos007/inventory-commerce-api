<?php
namespace App\Models;

use App\Observers\PaymentObserver;
use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[ObservedBy([PaymentObserver::class])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;
    use Scopeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone_number',
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
        'verifier_id',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['is_paid', 'is_verified'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Check if payment is paid
     */
    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->paid_at)
        );
    }

    /**
     * Check if payment is verified
     */
    protected function isVerified(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->verified_at)
        );
    }

    /**
     * Check if payment is successful
     */
    protected function isSuccessful(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'completed' && $this->is_paid
        );
    }

    /**
     * Check if payment is pending
     */
    protected function isPending(): Attribute
    {
        return Attribute::make(
            get: fn () => in_array($this->status, ['initiated', 'pending'])
        );
    }

    /**
     * Check if payment failed
     */
    protected function isFailed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'failed'
        );
    }

    // ============================================
    // STATUS SCOPES
    // ============================================

    /**
     * Scope: Filter by payment status
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Completed payments
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Pending payments
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['initiated', 'pending']);
    }

    /**
     * Scope: Failed payments
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Cancelled payments
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope: Successful payments (completed and paid)
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'completed')
            ->whereNotNull('paid_at');
    }

    // ============================================
    // PAYMENT STATE SCOPES
    // ============================================

    /**
     * Scope: Paid payments
     */
    public function scopeIsPaid(Builder $query, bool $isPaid = true): Builder
    {
        return $query->when($isPaid, function (Builder $query) {
            $query->whereNotNull('paid_at');
        }, function (Builder $query) {
            $query->whereNull('paid_at');
        });
    }

    /**
     * Scope: Verified payments
     */
    public function scopeIsVerified(Builder $query, bool $isVerified = true): Builder
    {
        return $query->when($isVerified, function (Builder $query) {
            $query->whereNotNull('verified_at');
        }, function (Builder $query) {
            $query->whereNull('verified_at');
        });
    }

    /**
     * Scope: Unverified payments
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('verified_at')
            ->whereNotNull('paid_at');
    }

    // ============================================
    // DATE RANGE SCOPES
    // ============================================

    /**
     * Scope: Payments created between dates
     */
    public function scopeCreatedBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Payments paid between dates
     */
    public function scopePaidBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('paid_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Payments verified between dates
     */
    public function scopeVerifiedBetween(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('verified_at', [$startDate, $endDate]);
    }

    /**
     * Scope: Today's payments
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope: Yesterday's payments
     */
    public function scopeYesterday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today()->subDay());
    }

    /**
     * Scope: This week's payments
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope: This month's payments
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereBetween('created_at', [
            now()->startOfMonth(),
            now()->endOfMonth()
        ]);
    }

    /**
     * Scope: This year's payments
     */
    public function scopeThisYear(Builder $query): Builder
    {
        return $query->whereYear('created_at', now()->year);
    }


    // ============================================
    // AMOUNT SCOPES
    // ============================================

    /**
     * Scope: Payments within amount range
     */
    public function scopeAmountBetween(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('amount', [$min, $max]);
    }

    /**
     * Scope: Payments above amount
     */
    public function scopeAmountAbove(Builder $query, float $amount): Builder
    {
        return $query->where('amount', '>', $amount);
    }

    /**
     * Scope: Payments below amount
     */
    public function scopeAmountBelow(Builder $query, float $amount): Builder
    {
        return $query->where('amount', '<', $amount);
    }

    /**
     * Scope: High value payments (above threshold)
     */
    public function scopeHighValue(Builder $query, float $threshold = 100000): Builder
    {
        return $query->where('amount', '>=', $threshold);
    }

    /**
     * Scope: Low value payments (below threshold)
     */
    public function scopeLowValue(Builder $query, float $threshold = 1000): Builder
    {
        return $query->where('amount', '<=', $threshold);
    }

    // ============================================
    // GATEWAY & METHOD SCOPES
    // ============================================

    /**
     * Scope: Filter by payment gateway
     */
    public function scopeGateway(Builder $query, string $gatewayId): Builder
    {
        return $query->where('payment_gateway_id', $gatewayId);
    }

    /**
     * Scope: Filter by payment method
     */
    public function scopeMethod(Builder $query, string $method): Builder
    {
        return $query->where('method', $method);
    }

    /**
     * Scope: Filter by currency
     */
    public function scopeCurrency(Builder $query, string $currency): Builder
    {
        return $query->where('currency', $currency);
    }

    // ============================================
    // USER SCOPES
    // ============================================

    /**
     * Scope: Filter by user
     */
    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Guest payments (no user)
     */
    public function scopeGuest(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * Scope: Registered user payments
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    // ============================================
    // ANALYTICS SCOPES
    // ============================================

    /**
     * Scope: Total payments revenue
     */
    public function scopeTotalRevenue(Builder $query): float
    {
        return $query->successful()->sum('amount');
    }

    /**
     * Scope: Average payment amount
     */
    public function scopeAverageAmount(Builder $query): float
    {
        return $query->successful()->avg('amount') ?? 0;
    }

    /**
     * Scope: Count by status
     */
    public function scopeCountByStatus(Builder $query): array
    {
        return $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Scope: Revenue by gateway
     */
    public function scopeRevenueByGateway(Builder $query)
    {
        return $query->successful()
            ->select('payment_gateway_id', DB::raw('SUM(amount) as total_revenue'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('payment_gateway_id')
            ->with('gateway:id,name');
    }

    /**
     * Scope: Revenue by currency
     */
    public function scopeRevenueByCurrency(Builder $query)
    {
        return $query->successful()
            ->select('currency', DB::raw('SUM(amount) as total_revenue'), DB::raw('COUNT(*) as transaction_count'))
            ->groupBy('currency');
    }

    /**
     * Scope: Daily revenue (for charts)
     */
    public function scopeDailyRevenue(Builder $query, int $days = 30)
    {
        return $query->successful()
            ->where('paid_at', '>=', now()->subDays($days))
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as revenue'), DB::raw('COUNT(*) as transactions'))
            ->groupBy('date')
            ->orderBy('date');
    }

    /**
     * Scope: Monthly revenue
     */
    public function scopeMonthlyRevenue(Builder $query, int $months = 12)
    {
        return $query->successful()
            ->where('paid_at', '>=', now()->subMonths($months))
            ->select(
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month');
    }

    // ============================================
    // RISK & FRAUD DETECTION SCOPES
    // ============================================

    /**
     * Scope: Multiple failed payments from same IP
     */
    public function scopeFailedFromIp(Builder $query, string $ipAddress, int $hours = 24): Builder
    {
        return $query->where('ip_address', $ipAddress)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours($hours));
    }


    /**
     * Scope: Suspicious payments (multiple pending/failed)
     */
    public function scopeSuspicious(Builder $query, int $hours = 1): Builder
    {
        return $query->whereIn('status', ['pending', 'failed'])
            ->where('created_at', '>=', now()->subHours($hours))
            ->select('email', 'ip_address', DB::raw('COUNT(*) as attempt_count'))
            ->groupBy('email', 'ip_address')
            ->havingRaw('COUNT(*) >= 3');
    }

    // ============================================
    // SEARCH SCOPE
    // ============================================

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('full_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone_number', 'like', "%{$term}%")
                ->orWhere('currency', 'like', "%{$term}%")
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

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get all the payables
     */
    public function payables(): HasMany
    {
        return $this->hasMany(Payable::class, 'payment_id');
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment gateway
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * Get the verifier (admin who verified)
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Generate unique transaction reference
     */
    public static function genTranxRef(): string
    {
        return 'TXN_' . strtoupper((string) Str::ulid());
    }

    /**
     * Mark payment as paid
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'paid_at' => now(),
            'status' => 'completed',
        ]);
    }

    /**
     * Mark payment as verified
     */
    public function markAsVerified(?string $verifierId = null): bool
    {
        return $this->update([
            'verified_at' => now(),
            'verifier_id' => $verifierId ?? auth()->id(),
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = null): bool
    {
        $data = ['status' => 'failed'];

        if ($reason) {
            $metadata = $this->metadata ?? [];
            $metadata['failure_reason'] = $reason;
            $data['metadata'] = $metadata;
        }

        return $this->update($data);
    }

    /**
     * Check if payment can be refunded
     */
    public function canBeRefunded(): bool
    {
        return $this->is_paid &&
            $this->status === 'completed' &&
            $this->paid_at->diffInDays(now()) <= 90; // 90 days refund window
    }
}
