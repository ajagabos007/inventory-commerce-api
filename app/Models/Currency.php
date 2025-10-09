<?php

namespace App\Models;

use App\Observers\CurrencyObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([CurrencyObserver::class])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'is_default',
        'exchange_rate',
        'disabled_at',
        'disabled_reason',
    ];

    /**
     * Name accessor
     */
    protected function isDisabled(): Attribute
    {
        return Attribute::make(
            get: fn () => !blank($this->disabled_at)
        );
    }
    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('symbol', 'like', "%{$term}%")
                ->orWhere('disabled_reason', 'like', "%{$term}%");
        });
    }

    /**
     * Scope currency disabled
     */
    public function scopeDisabled($query, $disabled = true)
    {
        $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);

        return  $query->when($disabled, function ($query) {
                     $query->whereNotNull('disabled_at');
                }, function ($query) {
                     $query->whereNull('disabled_at');
                });
    }
}
