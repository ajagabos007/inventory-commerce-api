<?php

namespace App\Traits;

use App\Models\ModelView;
use App\Models\ModelViewStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasViews
{
    public function views()
    {
        return $this->morphMany(ModelView::class, 'viewable');
    }

    /**
     * Record a view manually or automatically
     */
    public function recordView(int $timeoutMinutes = 10): void
    {
        if (app()->runningInConsole()) return;       // prevent CLI/queue views
        if (!request()) return;                      // prevent jobs
        if (request()->isJson()) return;             // prevent API JSON hits

        $userId = Auth::check() ? Auth::id() : (Auth::guard('sanctum')->check() ? Auth::guard('sanctum')->id() : null);

        $sessionId = session()->getId();
        if (request()->expectsJson()) {
            $sessionId = request()->header('X-Session-Token') ?: $sessionId;
        }

        $baseQuery = ModelView::where('viewable_type', static::class)
            ->where('viewable_id', $this->id);

        if ($userId) {
            $baseQuery->where('user_id', $userId);
        } else {
            $baseQuery->where('session_id', $sessionId);
        }

        $baseQuery->where('viewed_at', '>=', now()->subMinutes($timeoutMinutes));

        if ($baseQuery->exists()) return;

        ModelView::create([
            'viewable_id'   => $this->id,
            'viewable_type' => static::class,
            'user_id'       => $userId,
            'session_id'    => $sessionId,
            'ip_address'    => request()->ip(),
            'user_agent'    => request()->userAgent(),
            'viewed_at'     => now(),
        ]);
    }

    public function totalViews(): int
    {
        return $this->views()->count();
    }

    public function uniqueViews(): int
    {
        return $this->views()
            ->distinct('ip_address', 'session_id', 'user_id')
            ->count();
    }

    public function viewStats()
    {
        return $this->morphMany(ModelViewStat::class, 'viewable');
    }

    /**
     * Scope: Get models ordered by most recently viewed.
     *
     * @param Builder $query
     * @param int|null $minutes Number of minutes to filter by (optional)
     */
    public function scopeRecentlyViewed(Builder $query, int $minutes = null)
    {
        $viewsTable = (new static)->views()->getRelated()->getTable();

        $query->select($query->getModel()->getTable().'.*')
            ->leftJoin($viewsTable, function ($join) use ($viewsTable, $query) {
                $join->on($viewsTable.'.viewable_id', '=', $query->getModel()->getTable().'.id')
                    ->where($viewsTable.'.viewable_type', '=', $query->getModel()::class);
            });

        if ($minutes) {
            $query->where($viewsTable.'.viewed_at', '>=', now()->subMinutes($minutes));
        }

        return $query->orderBy($viewsTable.'.viewed_at', 'desc');
    }

    /**
     * Handle attachable delete event
     */
    public static function bootHasViews(): void
    {
        /**
         * Automatically record view
         * We only track views when:
         * - Running in web context (not queue, not CLI)
         * - Model is retrieved via controller/view (not via queries or jobs)
         */
        static::retrieved(function ($model) {
            // Only track view for GET requests
            if (!request()->isMethod('get')) {
                return;
            }

            // Model must be retrieved from a SHOW route (e.g., /model/{model})
            // Avoid list pages or relationship loading
            if (!request()->routeIs('*.show')) {
                return;
            }

            // Prevent double counts within the same request
            if ($model->hasBeenViewedThisRequest ?? false) {
                return;
            }
            $model->hasBeenViewedThisRequest = true;

            // Only record views when not explicitly disabled on the model instance
            if (!property_exists($model, 'autoRecordViews') || $model->autoRecordViews !== false) {
                $model->recordView();
            }
        });

        static::deleted(function ($model) {
            $model->views()->delete();
            $model->viewStats()->delete();
        });
    }
}
