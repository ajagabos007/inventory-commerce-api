<?php

namespace App\Http\Controllers\ECommerce;

// use Hnooz\LaravelCart\Facades\Cart;
use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\ECommerce\AddProductVariantToCartRequest;
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
                'message' => 'Cart items retrieved successfully',
            ]);
    }

    /**
     * Add item to cart
     */
    public function add(AddProductVariantToCartRequest $request)
    {
        $validated = $request->validated();

        $productVariantId = data_get($validated, 'product_variant_id');
        $productId = data_get($validated, 'product_id');

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

        return CartResource::collection($products)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Item increased successfully',
            ]);
    }

    public function decrease(string $id, Request $request)
    {

        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        Cart::decrease($id, $validated['quantity'] ?? 1);

        $items = Cart::all();

        return CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Item decreased successfully',
            ]);
    }

    public function remove(string $id, Request $request)
    {

        Cart::remove($id);

        $items = Cart::all();

        return CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Item remove from cart successfully',
            ]);
    }

    public function clear(Request $request)
    {
        Cart::clear();

        $items = Cart::all();

        return CartResource::collection($items)
            ->additional([
                'sub_total' => Cart::total(),
                'status' => 'success',
                'message' => 'Cart cleared successfully',
            ]);
    }

    public function checkout(Request $request) {}
}
