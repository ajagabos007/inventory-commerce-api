<?php

namespace App\Http\Controllers\ECommerce;

use App\Facades\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\POSCheckoutRequest;
use App\Http\Resources\SaleResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Inventory;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Managers\CheckoutManager;
Use App\Models\PaymentGateway;



class CheckoutController extends Controller
{

    /**
     * Generate or update the current order summary.
     *
     * @return array
     */
    public function summary(): array
    {
        // Retrieve cart items
        $items = Cart::all();
        $totalPrice = 0;

        // Calculate item totals
        foreach ($items as $index => $item) {
            $itemTotal = (float) $item['price'] * (int) $item['quantity'];
            $items[$index]['total'] = $itemTotal;
            $totalPrice += $itemTotal;
        }

        // Get payment gateway (fallback if none set)
        $paymentGateway = PaymentGateway::enabled()->default()->first();

        // Get the authenticated user (if any)
        $user = auth()->user();

        // Retrieve existing summary from session
        $summaryKey = CheckoutManager::DEFAULT_INSTANCE;
        $existingSummary = session($summaryKey);

        // If no summary exists, create a new one
        if (blank($existingSummary)) {
            $order = new Order([
                'user_id'        => $user?->id,
                'subtotal_price' => $totalPrice,
                'total_price'    => $totalPrice,
            ]);

            $summary = [
                'order'              => $order,
                'items'              => $items,
                'payment_gateway_id' => $paymentGateway?->id,
            ];
        } else {
            // Otherwise, update the existing summary
            $summary = $existingSummary;
            $summary['order']->subtotal_price = $totalPrice;
            $summary['order']->total_price = $totalPrice;
            $summary['items'] = $items;

            $summary['payment_gateway_id'] = $summary['payment_gateway_id']
                ?? $paymentGateway?->id;
        }

        // Persist updated summary in session
        session([$summaryKey => $summary]);

        return $summary;
    }

    public function addCoupon(){

    }
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
