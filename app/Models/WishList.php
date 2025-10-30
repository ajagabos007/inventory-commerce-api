<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Database\Factories\WishListFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WishList extends Model
{
    /** @use HasFactory<WishListFactory> */
    use HasFactory;
    use HasUuids;
    use ModelRequestLoader;
    use Scopeable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'item_id',
        'options',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array'
        ];
    }

    /**
     * Scope a query to only include wish lists by user session.
     */
    public function scopeByUserSession(Builder $query): Builder
    {
        $token = request()->header('x-session-token');

        $user = auth()->user() ?? auth()->guard('sanctum')->user();

        return match (true) {
            $user => $query->where('user_id', $user->id),
            $token => $query->where('session_token', $token),
            default => $query->whereRaw('1 = 0'), // No match
        };
    }

}
