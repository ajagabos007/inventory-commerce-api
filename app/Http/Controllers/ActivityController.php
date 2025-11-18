<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ActivityController extends Controller
{
    public function index()
    {
        $activityQ = Activity::query();

        $activities = QueryBuilder::for($activityQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'causer',
                'subject',
            ])
            ->allowedFilters([
                'user_id',
                'subject_id',
                'subject_type',
                'causer_id',
                'causer_type',

            ])
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            })
            ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = request()->per_page;
                $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

                return $query->paginate($perPage)
                    ->appends(request()->query());
            }, function ($query) {
                return $query->get();
            });

        return OrderResource::collection($activities)->additional([
            'status' => 'success',
            'message' => 'Activities Log retrieved successfully',
        ]);
    }
}
