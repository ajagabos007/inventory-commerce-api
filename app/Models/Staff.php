<?php

namespace App\Models;

use App\Observers\StaffObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[ObservedBy([StaffObserver::class])]
class Staff extends Model
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'staff_no',
        'store_id',
    ];

    /**
     * Scope a query to only include staff for the current store.
     */
    public function scopeForCurrentStore(Builder $query): Builder
    {
        if (app()->bound('currentStoreId')) {
            return $query->where('store_id', app('currentStoreId'));
        }

        return $query;
    }

    /**
     * Get the user account of the staff
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the store managed by the staff.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Get the store managed by the staff.
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_staff_id');
    }

    /**
     * Generate staff no.
     */
    public static function generateStaffNo(): string
    {
        $staff_no = '';
        $maxAttempts = 100;
        $attempt = 0;

        do {

            if (++$attempt > $maxAttempts) {
                throw new \RuntimeException("Unable to generate a unique staff no after {$attempt} attempts.");
            }

            $staff_no = 'GW'.Str::random(8); // Generate a random staff no. with prefix 'GW'

        } while (self::where('staff_no', $staff_no)->exists());

        return $staff_no;
    }
}
