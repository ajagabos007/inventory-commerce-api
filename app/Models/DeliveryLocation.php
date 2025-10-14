<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use Database\Factories\DeliveryLocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

class DeliveryLocation extends Model
{
    /** @use HasFactory<DeliveryLocationFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'country_id',
        'state_id',
        'city_id',
        'delivery_fee',
        'estimated_delivery_days',
    ];

    /**
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) {});
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
