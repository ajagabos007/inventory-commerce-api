<?php

namespace App\Models;

use App\Observers\PermissionObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;
use App\Enums\Permission as PermissionEnum;

#[ObservedBy(PermissionObserver::class)]
class Permission extends SpatiePermission
{
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['label'];

    public function scopeSearch(Builder $query, $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->whereAny([
                'name', 'guard_name'
            ], 'like', '%' . $term . '%');
        });
    }

    /**
     * Label accessor
     */
    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn () => PermissionEnum::tryFrom($this->name)->label(),
        );
    }
}
