<?php

namespace App\Managers;

use App\Facades\Cart;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Discount;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class CheckoutManager
{
    const SESSION_KEY = 'checkout-session';

    /**
     * Clear the entire checkout session
     */
    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Initialize or retrieve checkout session
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function initializeSession(): array
    {
        $sessionData = session(self::SESSION_KEY);

        if (blank($sessionData)) {
            $sessionData = $this->createNewSession();
            $this->persistSession($sessionData);
        }

        return $this->refreshOrderTotals($sessionData);
    }

    /**
     * Create a new checkout session
     */
    protected function createNewSession(): array
    {
        $user = auth()->user();
        $items = Cart::all();
        $paymentGateway = PaymentGateway::enabled()->default()->first();

        $order = new Order([
            'user_id' => $user?->id,
            'subtotal_price' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'total_price' => 0,
        ]);

        return [
            'order' => $order,
            'items' => $items,
            'payment_gateway_id' => $paymentGateway?->id,
            'payment_gateway' => $paymentGateway->only(['id','name','code', 'logo_url']),
            'coupon_code' => null,
            'coupon_discount' => 0,
            'billing_address' => null,
            'delivery_address' => null,
        ];
    }

    /**
     * Retrieve current session data
     */
    public function getSession(): ?array
    {
        return session(self::SESSION_KEY);
    }

    /**
     * Get the order from session
     */
    public function getOrder(): ?Order
    {
        $session = $this->getSession();
        return $session['order'] ?? null;
    }

    /**
     * Update order in session
     */
    public function updateOrder(array $attributes): self
    {
        $session = $this->getSession() ?? $this->createNewSession();

        foreach ($attributes as $key => $value) {
            $session['order']->$key = $value;
        }

        $this->persistSession($session);
        return $this;
    }

    /**
     * Update items in session from cart
     */
    public function syncItems(): self
    {
        $session = $this->getSession() ?? $this->createNewSession();
        $items = Cart::all();

        $session['items'] = $items;
        $this->persistSession($session);

        return $this->recalculateTotals();
    }

    /**
     * Set payment gateway
     */
    public function setPaymentGateway(int $gatewayId): self
    {
        $session = $this->getSession() ?? $this->createNewSession();

        $gateway = PaymentGateway::enabled()->find($gatewayId);

        if (!$gateway) {
            throw new \InvalidArgumentException("Invalid payment gateway ID: {$gatewayId}");
        }

        $session['payment_gateway_id'] = $gatewayId;
        $session['payment_gateway'] = $gateway->only(['id','name','code', 'logo_url']);

        $this->persistSession($session);

        return $this;
    }

    /**
     * Get payment gateway ID
     */
    public function getPaymentGatewayId(): ?int
    {
        $session = $this->getSession();
        return $session['payment_gateway_id'] ?? null;
    }

    /**
     * Apply coupon code
     */
    public function applyDiscount(string $couponCode): self
    {
        $session = $this->getSession() ?? $this->createNewSession();

        $coupon = Discount::valid()
            ->where('code', $couponCode)
            ->first();

        if (!$coupon) {
            throw new \InvalidArgumentException("Invalid or expired coupon code");
        }

        $session['coupon_code'] = $couponCode;
        $session['discount_id'] = $coupon->id;
        $session['discount'] = $coupon->only(['id','code','percentage']);

        $this->persistSession($session);
        return $this->recalculateTotals();
    }

    /**
     * Remove coupon
     */
    public function removeDiscount(): self
    {
        $session = $this->getSession();

        if ($session) {
            $session['coupon_code'] = null;
            $session['discount_id'] = null;
            $session['coupon_discount'] = 0;

            $this->persistSession($session);
            $this->recalculateTotals();
        }

        return $this;
    }

    /**
     * Set billing address
     */
    public function setBillingAddress(array $address): self
    {
        $session = $this->getSession() ?? $this->createNewSession();
        $session['billing_address'] = $address;

        $this->persistSession($session);
        return $this;
    }

    /**
     * Set shipping address
     */
    public function setShippingAddress(array $address): self
    {
        $session = $this->getSession() ?? $this->createNewSession();
        $session['delivery_address'] = $address;

        $this->persistSession($session);
        return $this;
    }

    /**
     * Get billing address
     */
    public function getBillingAddress(): ?array
    {
        $session = $this->getSession();
        return $session['billing_address'] ?? null;
    }

    /**
     * Get shipping address
     */
    public function getShippingAddress(): ?array
    {
        $session = $this->getSession();
        return $session['delivery_address'] ?? null;
    }

    /**
     * Recalculate order totals based on items and coupon
     */
    protected function recalculateTotals(): self
    {
        $session = $this->getSession();

        if (!$session) {
            return $this;
        }

        $items = $session['items'] ?? [];
        $subtotal = 0;

        // Calculate subtotal
        foreach ($items as $index => $item) {
            $itemTotal = (float) $item['price'] * (int) $item['quantity'];
            $items[$index]['total'] = $itemTotal;
            $subtotal += $itemTotal;
        }

        // Calculate discount
        $discount = 0;
        if (!empty($session['discount_id'])) {
            $coupon = Discount::find($session['discount_id']);

            if ($coupon) {
                $discount = ($subtotal * $coupon->value) / 100;

//                if ($coupon->type === 'percentage') {
//                    $discount = ($subtotal * $coupon->value) / 100;
//                } elseif ($coupon->type === 'fixed') {
//                    $discount = min($coupon->value, $subtotal);
//                }
            }
        }

        // Update order totals
        $session['order']->subtotal_price = $subtotal;
        $session['order']->discount_amount = $discount;
        $session['coupon_discount'] = $discount;

        // Calculate final total (you can add tax and shipping here)
        $total = $subtotal - $discount;
        $total += $session['order']->tax_amount ?? 0;
        $total += $session['order']->shipping_cost ?? 0;

        $session['order']->total_price = max(0, $total);
        $session['items'] = $items;

        $this->persistSession($session);
        return $this;
    }

    /**
     * Refresh order totals (called during initialization)
     */
    protected function refreshOrderTotals(array $session): array
    {
        $items = Cart::all();
        $session['items'] = $items;

        $this->persistSession($session);
        $this->recalculateTotals();

        return $this->getSession();
    }

    /**
     * Create and save the order to database
     */
    public function createOrder(): Order
    {
        $session = $this->getSession();

        if (!$session) {
            throw new \RuntimeException("No checkout session found");
        }

        $order = $session['order'];

        // Set payment gateway
        if (!empty($session['payment_gateway_id'])) {
            $order->payment_gateway_id = $session['payment_gateway_id'];
        }

        // Set coupon
        if (!empty($session['discount_id'])) {
            $order->discount_id = $session['discount_id'];
        }

        // Set addresses
        if (!empty($session['billing_address'])) {
            $order->billing_address = $session['billing_address'];
        }

        if (!empty($session['delivery_address'])) {
            $order->delivery_address = $session['delivery_address'];
        }

        // Save the order
        $order->save();

        // Attach order items
        $items = $session['items'] ?? [];
        foreach ($items as $item) {
            $order->items()->create([
                'product_id' => $item['id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'total' => $item['total'],
            ]);
        }

        return $order;
    }

    /**
     * Validate checkout before proceeding to payment
     */
    public function validate(): bool
    {
        $session = $this->getSession();

        if (!$session) {
            return false;
        }

        // Check if items exist
        if (empty($session['items'])) {
            return false;
        }

        // Check if payment gateway is set
        if (empty($session['payment_gateway_id'])) {
            return false;
        }

        // Check if billing address is set
        if (empty($session['billing_address'])) {
            return false;
        }

        return true;
    }

    /**
     * Proceed to payment (creates order and returns payment URL/data)
     */
    public function proceedToPayment(): array
    {
        if (!$this->validate()) {
            throw new \RuntimeException("Checkout validation failed");
        }

        // Create the order
        $order = $this->createOrder();

        // Get payment gateway
        $gateway = PaymentGateway::find($this->getPaymentGatewayId());

        // Clear cart and checkout session
        Cart::clear();
        self::clear();

        return [
            'order' => $order,
            'payment_gateway' => $gateway,
            'redirect_url' => route('payment.process', ['order' => $order->id]),
        ];
    }

    /**
     * Persist session data
     */
    protected function persistSession(array $session): void
    {
        session([self::SESSION_KEY => $session]);
    }

    /**
     * Get summary for display
     */
    public function getSummary(): array
    {
        return $this->initializeSession();
    }
}
