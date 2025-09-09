<?php

namespace App\Http\Controllers;

use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class ActiveSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD
     */
    public function index(Builder|Relation|string|null $subject = null)
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $personal_access_tokens = QueryBuilder::for($subject ?? PersonalAccessToken::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'name',
                'last_used_at',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'tokenable',
            ])
            ->allowedFilters([
                'user_id',
                'name',
                'tokenable_type',
                'tokenable_id',
            ]);

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

            $personal_access_tokens = $personal_access_tokens->paginate($perPage)
                ->appends(request()->query());

        } else {
            $personal_access_tokens = $personal_access_tokens->get();
        }

        $personal_access_tokens_collection = PersonalAccessTokenResource::collection($personal_access_tokens)->additional([
            'message' => 'Active session successfully',
        ]);

        return $personal_access_tokens_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PersonalAccessToken $active_session)
    {
        $active_session->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Session loggedout successfully.',
        ], 200);
    }
}
