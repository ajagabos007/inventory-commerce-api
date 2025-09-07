<?php

namespace App\Models;

use App\Observers\ScrapeObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([ScrapeObserver::class])]
class Scrape extends Model
{
    /** @use HasFactory<\Database\Factories\ScrapeFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'inventory_id',
        'quantity',
        'customer_id',
        'staff_id',
        'comment',
        'type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'json',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('store', function (Builder $builder) {
            if (app()->runningInconsole()) {
                return;
            }
            $builder->whereHas('inventory');
        });
    }

    /**
     * Get the customer associated with the scrape.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the staff associated with the scrape.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'staff_id');
    }

    /**
     * Get the inventory
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }
}
