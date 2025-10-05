<?php

namespace App\Http\Controllers\POS;

// use Hnooz\LaravelCart\Facades\Cart;
use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\POS\AddProductVariantToCartRequest;
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
        $items = Cart::all();

        return CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Cart products retrieved successfully',
            ]);
    }

    /**
     * Add item to cart
     */
    public function add(AddProductVariantToCartRequest $request)
    {
        $validated = $request->validated();

        $inventory = Inventory::whereHas('productVariant', function ($query) use ($validated) {
            $query->where('sku', $validated['sku']);
        })
            ->with('productVariant')
            ->first();

        if (! $inventory) {
            return response()->json([
                'status' => 'error',
                'message' => 'No item in  found inventory',
            ], 422);
        }
        $image = $inventory->productVariant->images()->first();
        $image_url = null;
        if (! blank($image)) {
            $image->append('url');
            $image_url = $image->toArray()['url'] ?? null;
        }

        $options = [
            'image_url' => $image_url,
            'itemable_id' => $inventory->id,
            'itemable_type' => get_class($inventory),
            'price' => $inventory->productVariant->price,
        ];

        Cart::add($inventory->id, $inventory->productVariant->name, $inventory->productVariant->price, $validated['quantity'] ?? 1, $options);

        $items = Cart::all();

        $cart_collection = CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Item added to cart successfully',
            ]);

        return $cart_collection;

    }

    public function update(string $id, Request $request)
    {

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        Cart::update($id, $validated);

        $items = Cart::all();

        return CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Item updated successfully',
            ]);
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
