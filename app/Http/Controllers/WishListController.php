<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishListResource;
use App\Managers\WishListManager;
use App\Models\WishList;
use Illuminate\Http\Request;

class WishListController extends Controller
{
    protected WishListManager $wishListManager;

    public function __construct()
    {
        $this->authorizeResource(WishList::class, 'wishList');
        $this->wishListManager = new WishListManager;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $items = $this->wishListManager->all();

        return WishListResource::collection($items)
            ->additional([
                'message' => 'wish list retrieved successfully',
            ]);
    }

    public function store(Request $request)
    {
        $wishlist = $this->wishListManager->add(
            $request->item_type,
            $request->item_id,
            $request->only('name', 'price', 'image'),
            $request->input('options', [])
        );

        return WishListResource::collection($wishlist)
            ->additional([
                'message' => 'wish list retrieved successfully',
            ]);
    }

    public function destroy(WishList $wishList)
    {
        $wishList->delete();

        return (new WishListResource(null))->additional([
            'message' => 'Store deleted successfully',
        ]);
    }
}
