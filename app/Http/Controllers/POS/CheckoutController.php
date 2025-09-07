<?php

namespace App\Http\Controllers\POS;

use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\POSCheckoutRequest;
use App\Http\Resources\SaleResource;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(POSCheckoutRequest $request)
    {

        $products = Cart::all();

        if (count($products) == 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'POS checkout failed, cart is empty',
            ], 422);
        }

        $validated = $request->validated();

        DB::beginTransaction();

        try {

            $subtotal_price = Cart::total();

            $discount = Discount::where('code', $validated['discount_code'] ?? null)->first();

            if ($discount) {
                $total_price = $subtotal_price - ($subtotal_price * (1 / 100 * $discount->percentage ?? 0));
            } else {
                $total_price = $subtotal_price;
            }

            // Calculate tax and total
            $sale = Sale::create([
                'user_id' => null,
                'payment_method' => $request->payment_method,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_name,
                'customer_phone_number' => $request->customer_phone_number,
                'customer_email' => $request->customer_email,
                'discount_id' => is_null($discount) ? null : $discount->id ?? null,
                'tax' => $request->tax ?? 0,
                'subtotal_price' => $subtotal_price,
                'total_price' => $total_price,
            ]);

            $metadata['discount'] = $discount;
            $sale->metadata = $metadata;
            $sale->save();

            $inventories = Inventory::whereIn('id', array_column($products, 'id'))->get();

            $sale_item_data = [];
            foreach ($products as $key => $item) {
                $invent = $inventories->firstWhere('id', $item['id']);

                if (blank($invent)) {
                    continue;
                }

                if ($invent->quantity > $item['quantity']) {
                    $invent->decrement('quantity', $item['quantity']);
                } else {
                    $invent->update([
                        'quantity' => 0,
                    ]);
                }

                $sale_item_data[] = [
                    'inventory_id' => $invent->id,
                    'quantity' => $item['quantity'],
                    'weight' => $invent->item->weight,
                    'price_per_gram' => $item['price'],
                    'total_price' => $item['price'] * $item['quantity'],
                    'daily_gold_price_id' => data_get($item, 'daily_gold_price_id', null),
                ];
            }

            $sale->saleInventories()->createMany($sale_item_data);

            DB::commit();

            Cart::clear();

            $sale->load('saleInventories.inventory.item.category');

            $sale_resource = (new SaleResource($sale))->additional([
                'message' => 'Checkout successfuly successfully',
            ]);

            return $sale_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to create sale.',
                'errors' => ['create_sale' => $e->getMessage()],
            ], 500);
        }

    }
}
