<?php

namespace App\Http\Controllers\ECommerce;

use App\Exceptions\CheckoutValidationException;
use App\Exceptions\PaymentException;
use App\Handlers\PaymentHandler;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckoutResource;
use App\Managers\CheckoutManager;
use App\Models\Payment;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    protected CheckoutManager $checkout;

    public function __construct()
    {
        $this->checkout = new CheckoutManager;
    }

    public function summary()
    {
        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Checkout summary',
        ]);
    }

    public function syncItems()
    {
        $this->checkout->syncItems();

        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Checkout items sync',
        ]);
    }

    public function setBillingAddress(Request $request)
    {
        $this->checkout->setBillingAddress($request->validate([
            'full_name' => 'required|string',
            'phone_number' => 'required|string',
            'address_line' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
        ]));

        return response()->json($this->checkout->getSummary());
    }

    public function setDeliveryAddress(Request $request)
    {
        $this->checkout->setDeliveryAddress($request->validate([
            'full_name' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
            'email' => ['required', 'email'],
            'address' => ['required', 'string'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'state_id' => ['required', 'exists:states,id'],
            'country_id' => ['required', 'exists:countries,id'],
        ]));

        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Delivery address set successfully',
        ]);
    }

    public function setPaymentGateway(Request $request)
    {
        $this->checkout->setPaymentGateway(
            $request->validate(['payment_gateway_id' => 'required|exists:payment_gateways,id'])['payment_gateway_id']
        );

        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Payment gateway set successfully',
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $this->checkout->applyCoupon(
            $request->validate(['coupon_code' => 'required|string'])['coupon_code']
        );

        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Coupon applied successfully',
        ]);
    }

    public function removeCoupon()
    {
        $this->checkout->removeCoupon();

        return (new CheckoutResource($this->checkout->getSummary()))->additional([
            'message' => 'Coupon removed successfully',
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function confirmOrder(Request $request)
    {
        try {
            // This will validate and throw CheckoutValidationException if invalid
            $payment = $this->checkout->proceedToPayment($request->validate(
                [
                    'create_account' => 'nullable|boolean',
                    'callback_url' => 'nullable|url', 'cancel_url' => 'nullable|url'
                ]
            ));

            // Initialize payment with gateway
            $handler = new PaymentHandler($payment);
            $result = $handler->initializePayment();

            return response()->json([
                'success' => true,
                'message' => 'Payment initialized successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'checkout_url' => $payment->checkout_url,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            ]);

        } catch (CheckoutValidationException $e) {
            // Returns 422 with validation errors
            return $e->render();

        } catch (PaymentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());

        } catch (\Exception $e) {
            \Log::error('Checkout payment error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your checkout. Please try again.'.$e->getMessage(),
            ], 500);
        }

    }
}
