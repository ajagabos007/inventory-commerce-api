<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Spatie\QueryBuilder\QueryBuilder;

class CouponController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Coupon::class, 'coupon');
    }

    /**
     * Display a listing of the resource.
     *serve
     *
     * @method GET|HEAD /api/coupons
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $coupons = QueryBuilder::for(Coupon::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'name',
                'name',
                'email',
                'phone_number',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'user_id',
            ])
            ->allowedIncludes([
                'user',
                'user.roles.permissions',
                'user.roles',
            ]);

        $coupons->when(request()->filled('q'), function ($query) {
            $query->search(request()->q);
        });

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0', 'no'], true)) {

            $perPage = ! is_numeric($perPage) ? 15 : max(intval($perPage), 1);

            $coupons = $coupons->paginate($perPage)
                ->appends(request()->query());

        } else {
            $coupons = $coupons->get();
        }

        return CouponResource::collection($coupons)->additional([
            'status' => 'success',
            'message' => 'Coupons retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCouponRequest $request): CouponResource
    {
        $validated = $request->validated();

        $coupon = Coupon::firstOrCreate($validated);

        return (new CouponResource($coupon))->additional([
            'message' => 'Coupon created successfully',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Coupon $coupon): CouponResource
    {
        $coupon->loadFromRequest();

        return (new CouponResource($coupon))->additional([
            'message' => 'Coupon retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        $validated = $request->validated();
        $coupon->update($validated);

        return (new CouponResource($coupon))->additional([
            'message' => 'Coupon updated successfully',
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Coupon $coupon): CouponResource
    {
        $coupon->delete();

        return (new CouponResource(null))->additional([
            'message' => 'Coupon deleted successfully',
        ]);
    }
}
