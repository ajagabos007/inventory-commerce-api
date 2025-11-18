<?php

namespace App\Http\Controllers;

use App\Http\Resources\WishListResource;
use App\Managers\WishListManager;
use App\Models\Inventory;
use App\Models\WishList;
use Illuminate\Http\Request;

class WishListController extends Controller
{
    protected WishListManager $wishListManager;

    public function __construct()
    {
        $this->authorizeResource(WishList::class, 'wish_list');
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
        $validated = $request->all();
        $productVariantId = data_get($validated, 'product_variant_id', null);
        $productId = data_get($validated, 'product_id', null);

        $inventory = Inventory::whereHas('productVariant', function ($query) use ($productVariantId, $productId) {
            $query->when(! blank($productVariantId), function ($query) use ($productVariantId) {
                $query->where('id', $productVariantId);
            }, function ($query) use ($productId) {
                $query->where('product_id', $productId);
            });
        })
        ->with('productVariant')
        ->first();

        if (blank($inventory)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No item in found inventory',
            ], 422);
        }

        $image = $inventory->productVariant->images()->first();
        $image_url = null;

        if (! blank($image)) {
            $image->append('url');
            $image_url = $image->toArray()['url'] ?? null;
        }

        $wishlist = $this->wishListManager->add(
                    $inventory->id,
                    get_class($inventory),
                    $inventory->productVariant->name,
                    $inventory->productVariant->price,
                    [
                        'image_url' => $image_url,
                        'item' => $inventory->toArray(),
                    ]
                );

        return (new WishListResource($wishlist))
            ->additional([
                'message' => 'wish list retrieved successfully',
            ]);
    }


    /**
     * Display the specified resource.
     */
    public function show (WishList $wishList){
        $wishList->loadFromRequest();

        return (new WishListResource($wishList))->additional([
            'message' => 'Wish list item retrieved successfully',
        ]);
    }

    public function destroy(WishList $wishList)
    {
        $wishList->delete();

        return (new WishListResource(null))->additional([
            'message' => 'wishlist deleted successfully',
        ]);
    }
}
