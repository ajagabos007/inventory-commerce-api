<?php

namespace App\Models;

use App\Observers\DiscountObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([DiscountObserver::class])]
class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'description',
        'percentage',
        'expires_at',
        'is_active',
    ];

    /**
     * @param Builder $query
     * @param bool $is_valid
     * @return void
     */
    public function scopeValid(Builder $query, bool $is_valid=true): void
    {
        $query->when($is_valid, function (Builder $query) {
            $query->whereDate('expires_at', '>', now())
                ->where('is_active', true);
        }, function (Builder $query) {
            $query->whereDate('expires_at', '<=', now())
                ->orWhere('is_active', false);
        });
    }
}
