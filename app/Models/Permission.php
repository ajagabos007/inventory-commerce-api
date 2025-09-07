<?php

namespace App\Models;

use App\Observers\PermissionObserver;
use App\Traits\ModelRequestLoader;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[ObservedBy(PermissionObserver::class)]
class Permission extends SpatiePermission
{
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;
}
