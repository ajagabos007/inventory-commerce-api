<?php

namespace App\QueryBuilder\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class StaffIsManager implements Filter
{
    public function __invoke(Builder $query, $value, string $property)
    {
        if (in_array($value, [true, 'true', 1, '1'], true)) {
            $query->whereHas('managedStore');
        } elseif (in_array($value, [false, 'false', 0, '0'], true)) {
            $query->doesntHave('managedStore');
        }
    }
}
