<?php

namespace App\Http\Controllers\ECommerce;

use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\POSCheckoutRequest;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
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
        $items = Cart::all();

        if (count($items) == 0) {
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
                'payment_method' => $request->payment_method,
                'buyerable_id' => $request->customer_id,
                'buyerable_type' => Customer::class,
                'discount_id' => is_null($discount) ? null : $discount->id,
                'tax' => $request->tax ?? 0,
                'subtotal_price' => $subtotal_price,
                'total_price' => $total_price,
            ]);

            $metadata['discount'] = $discount;
            $sale->metadata = $metadata;
            $sale->save();

            $inventories = Inventory::whereIn('id', array_column($items, 'id'))->get();

            $sale_item_data = [];
            foreach ($items as $key => $item) {
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
                    'price' => $item['price'],
                    'total_price' => $item['price'] * $item['quantity'],
                ];
            }

            $sale->saleInventories()->createMany($sale_item_data);

            DB::commit();

            Cart::clear();

            $sale->load([
                'buyerable',
                'saleInventories.inventory.productVariant',
                'discount',
                'cashier.user',
            ]);

            return (new SaleResource($sale))->additional([
                'message' => 'Checkout successfully',
            ]);

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
