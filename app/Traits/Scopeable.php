<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait Scopeable
{
    public function scopeCreatedAfter(Builder $query, $date): Builder
    {
        return $query->where($this->getTable().'.created_at', '>=', Carbon::parse($date));
    }

    public function scopeCreatedBefore(Builder $query, $date): Builder
    {
        return $query->where($this->getTable().'.created_at', '<=', Carbon::parse($date));
    }
}
