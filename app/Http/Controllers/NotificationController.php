<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD
     */
    public function index(Request $request)
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $notifications = QueryBuilder::for(auth()->user()->notifications())
            ->defaultSort('-created_at')
            ->allowedSorts(
                'type',
                'read_at',
                'created_at',
            )
            ->allowedIncludes([
                'notifiable',
            ])
            ->allowedFilters([
                'notifiable_id',
                AllowedFilter::callback('read', function (Builder $query, $value) {
                    $query->when(boolval($value), function ($query) {
                        return $query->read();
                    }, function ($query) {
                        return $query->unread();
                    });
                }),
            ]);

        if (request()->filled('q')) {
            $notifications->where(function ($query) {
                $model = $query->getModel();
                $table = $model->getTable();
                $searchTerm = '%'.request()->q.'%';

                $cacheKey = "{$table}_column_listing";
                $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                    return Schema::getColumnListing($table);
                });

                foreach ($columns as $index => $column) {
                    $command = $index == 0 ? 'where' : 'orWhere';
                    $query->{$command}($column, 'like', $searchTerm);
                }

                $query->{$command}('data->title', 'like', $searchTerm)
                    ->orWhere('data->message', 'like', $searchTerm)
                    ->orWhere('data->type', 'like', $searchTerm);
            });
        }
        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0'], true)) {
            /**
             * Ensure per_page is integer and >= 1
             */
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $notifications = $notifications->paginate($perPage)
                ->appends(request()->query());

        } else {
            $notifications = $notifications->get();
        }

        $notifications_collection = NotificationResource::collection($notifications)->additional([
            'message' => 'Notifications retrieved successfully',
        ]);

        return $notifications_collection;
    }

    /**
     * Display the specified resource.
     */
    public function show(DatabaseNotification $notification)
    {
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $notification_resource = (new NotificationResource($notification))->additional([
            'message' => 'Notification retreived successfully',
        ]);

        return $notification_resource;
    }

    /**
     * Mark as read the specified resource.
     */
    public function markAsRead(DatabaseNotification $notification)
    {
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403, 'Unauthorized to modify this notification');
        }

        if (! is_null($notification->read_at)) {
            return (new NotificationResource($notification))->additional([
                'message' => 'Notification is already read',
            ]);
        }

        $notification->markAsRead();

        return (new NotificationResource($notification))->additional([
            'message' => 'Notification marked as read successfully',
        ]);
    }

    /**
     * Mark as read the specified resource.
     */
    public function markAsUnread(DatabaseNotification $notification)
    {
        if ($notification->notifiable_id !== auth()->id()) {
            abort(403, 'Unauthorized to modify this notification');
        }

        if (is_null($notification->read_at)) {
            return (new NotificationResource($notification))->additional([
                'message' => 'Notification is already unread',
            ]);
        }

        $notification->markAsUnread();

        return (new NotificationResource($notification))->additional([
            'message' => 'Notification marked as unread successfully',
        ]);

    }

    /**
     * Mark all as read the specified resource.
     */
    public function markAllAsRead(Request $request)
    {
        $user = auth()->user();
        $unreadIds = $user->unreadNotifications()
            ->pluck('id');

        $user->unreadNotifications()
            ->update(['read_at' => Date::now()]);

        $unread_notifications = $user->notifications()
            ->whereIn('id', $unreadIds)
            ->paginate();

        $notifications_collection = NotificationResource::collection($unread_notifications)->additional([
            'message' => 'All unread notifications marked read successfully',
        ]);

        return $notifications_collection;
    }

    /**
     * Mark all as un read the specified resource.
     */
    public function markAllAsUnread(Request $request)
    {
        $user = auth()->user();
        $readIds = $user->readNotifications()
            ->pluck('id');

        $user->readNotifications()
            ->update(['read_at' => null]);

        $read_notifications = $user->notifications()
            ->whereIn('id', $readIds)
            ->paginate();

        $notifications_collection = NotificationResource::collection($read_notifications)->additional([
            'message' => 'All read notifications marked unread successfully',
        ]);

        return $notifications_collection;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DatabaseNotification $notification)
    {
        auth()->user()
            ->notifications()
            ->where('id', $notification->id)
            ->delete();

        $notification_resource = (new NotificationResource(null))->additional([
            'message' => 'Notification deleted successfully',
        ]);

        return $notification_resource;
    }
}
