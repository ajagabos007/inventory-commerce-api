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

    /**
     * Scope: Last N days
     */
    public function scopeLastDays(Builder $query, int $days, $column='created_at'): Builder
    {
        return $query->where($query->qualifyColumn($column), '>=', now()->subDays($days));
    }

    /**
     * Scope: Filter by predefined periods
     */
    public function scopePeriod(Builder $query, $period, $column='created_at'): Builder
    {
        return match ($period) {
            'today' => $query->whereDate($query->qualifyColumn($column), Carbon::today()),
            'this_week' => $query->whereBetween($query->qualifyColumn($column), [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
            'this_month' => $query->whereMonth($query->qualifyColumn($column), Carbon::now()->month)
                ->whereYear($query->qualifyColumn($column), Carbon::now()->year),
            'this_year' => $query->whereYear($query->qualifyColumn($column), Carbon::now()->year),
            'yesterday' => $query->whereDate($query->qualifyColumn($column), Carbon::yesterday()),
            'last_week' => $query->whereBetween($query->qualifyColumn($column), [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]),
            'last_month' => $query->whereMonth($query->qualifyColumn($column), Carbon::now()->subMonth()->month)
                ->whereYear($query->qualifyColumn($column), Carbon::now()->subMonth()->year),
            'last_year' => $query->whereYear($query->qualifyColumn($column), Carbon::now()->subYear()->year),
            default => $query,
        };
    }

    /**
     * Scope: Filter between two dates
     */
    public function scopeDateBetween(Builder $query, $startDate, $endDate, $column='created_at'): Builder
    {
        return $query->whereBetween($query->qualifyColumn($column), [Carbon::parse($startDate), Carbon::parse($endDate)]);
    }
}
