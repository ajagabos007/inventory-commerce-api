<?php

namespace App\Http\Controllers\POS;

// use Hnooz\LaravelCart\Facades\Cart;
use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddProductToCartRequest;
use App\Http\Resources\POS\CartResource;
use App\Models\Inventory;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get all products in the cart
     */
    public function index()
    {
        $products = Cart::all();

        $products_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Cart products retrieved successfully',
            ]);

        return $products_collection;
    }

    /**
     * Add item to cart
     */
    public function add(AddProductToCartRequest $request)
    {
        $validated = $request->validated();

        $inventory = Inventory::belongingToCurrentStaff()
            ->whereHas('item', function ($query) use ($validated) {
                $query->where('sku', $validated['sku']);
            })
            ->first();

        if (! $inventory) {
            return response()->json([
                'status' => 'error',
                'message' => 'No item in  found inventory',
            ], 422);
        }

        $options = [
            'image_url' => $inventory->item->image?->url,
            'itemable_id' => $inventory->id,
            'itemable_type' => get_class($inventory),
            'daily_gold_price_id' => $inventory->item->dailyGoldPrices()->today()->first()?->id,
        ];

        Cart::add($inventory->id, $inventory->item->name, $inventory->item->price, $validated['quantity'] ?? 20, $options);

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Product added to cart successfully',
            ]);

        return $cart_collection;

    }

    public function update(string $id, Request $request)
    {

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
            'price' => ['nullable', 'integer', 'min:0'],
        ]);

        Cart::update($id, $validated);

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Product updated successfully',
            ]);

        return $cart_collection;
    }

    public function increase(string $id, Request $request)
    {

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        Cart::increase($id, $validated['quantity'] ?? 1);

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Product increased successfully',
            ]);

        return $cart_collection;
    }

    public function decrease(string $id, Request $request)
    {

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        Cart::decrease($id, $validated['quantity'] ?? 1);

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Product increased successfully',
            ]);

        return $cart_collection;
    }

    public function remove(string $id, Request $request)
    {

        Cart::remove($id);

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Product remove from cart successfully',
            ]);

        return $cart_collection;
    }

    public function clear(Request $request)
    {

        Cart::clear();

        $products = Cart::all();

        $cart_collection = CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Cart cleared successfully',
            ]);

        return $cart_collection;
    }

    public function checkout(Request $request) {}
}
