<?php

namespace App\Models;

use App\Observers\RoleObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

#[ObservedBy(RoleObserver::class)]
class Role extends SpatieRole
{
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;

    public function scopeSearch($query, $term)
    {
        return $query->whereAny(
            [
                'name', 
                'guard_name'
            ], 
            'like', "%{$term}%"
        );
    }
}
