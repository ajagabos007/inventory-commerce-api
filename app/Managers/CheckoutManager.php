<?php
namespace App\Managers;

use App\Facades\Cart;
use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Exceptions\CheckoutValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;

class CheckoutManager
{
    protected CheckoutSession $session;

    public function __construct()
    {
        $this->loadSession();
        $this->syncItems();
    }

    protected function loadSession(): void
    {
        $token = request()->header('x-session-token') ?? Str::uuid()->toString();

        $user = auth()->user() ?? auth()->guard('sanctum')->user();

        $this->session = match (true) {
            $token && ($existing = CheckoutSession::find($token)) => $existing,
            $user && ($existing = CheckoutSession::where('user_id', $user->id)->first()) => $existing,
            default => CheckoutSession::create([
                'id' => $token,
                'user_id' => $user?->id,
                'data' => $this->initialData(),
            ]),
        };
    }

    public function token(): string
    {
        return $this->session->id;
    }

    protected function getData(): array
    {
        return $this->session->data ?? [];
    }

    protected function save(array $data): void
    {
        $this->session->update(['data' => $data]);
    }

    protected function initialData(): array
    {
        $gateway = PaymentGateway::enabled()->default()->first();

        return [
            'order' => [
                'subtotal_price' => 0,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'shipping_cost' => 0,
                'total_price' => 0,
            ],
            'items' => Cart::all(),
            'payment_gateway_id' => $gateway?->id,
            'coupon_code' => null,
            'billing_address' => null,
            'delivery_address' => null,
        ];
    }

    public function syncItems(): self
    {
        $data = $this->getData();
        $data['items'] = Cart::all();
        $this->recalculateTotals($data);
        $this->save($data);

        return $this;
    }

    public function setPaymentGateway($id): self
    {
        $data = $this->getData();
        $gateway = PaymentGateway::enabled()->findOrFail($id);
        $data['payment_gateway_id'] = $id;
        $data['payment_gateway'] = $gateway->only(['id', 'name', 'code', 'logo_url']);
        $this->save($data);

        return $this;
    }

    public function applyCoupon(string $code): self
    {
        $coupon = Coupon::valid()->where('code', $code)->firstOrFail();
        $data = $this->getData();
        $data['coupon_id'] = $coupon->id;
        $data['coupon_code'] = $code;
        $this->recalculateTotals($data);
        $this->save($data);

        return $this;
    }

    public function removeCoupon(): self
    {
        $data = $this->getData();
        unset($data['coupon_id'], $data['coupon_code']);
        $this->recalculateTotals($data);
        $this->save($data);

        return $this;
    }

    public function setBillingAddress(array $address): self
    {
        $data = $this->getData();
        $data['billing_address'] = $address;
        $this->save($data);

        return $this;
    }

    public function setDeliveryAddress(array $address): self
    {
        $data = $this->getData();
        $country = Country::find(data_get($address, 'country_id'));
        if ($country) {
            unset($address['country_id']);
            $address['country'] = $country->toArray();
        }

        $state = State::find(data_get($address, 'state_id'));
        if ($state) {
            unset($address['state_id']);
            $address['state'] = $state->toArray();
        }

        $city = City::find(data_get($address, 'city_id'));
        if ($city) {
            unset($address['city_id']);
            $address['city'] = $city->toArray();
        }

        $data['delivery_address'] = $address;
        $this->save($data);

        return $this;
    }

    protected function recalculateTotals(array &$data): void
    {
        $items = $data['items'] ?? [];
        $subtotal = collect($items)->sum(fn ($i) => $i['price'] * $i['quantity']);
        $discount = 0;

        if (! empty($data['coupon_id'])) {
            $coupon = Coupon::find($data['coupon_id']);
            if ($coupon) {
                $discount = match ($coupon->type) {
                    'percentage' => ($subtotal * $coupon->value) / 100,
                    'fixed' => min($coupon->value, $subtotal),
                    default => 0,
                };
            }
        }

        $order = $data['order'] ?? [];
        $order['subtotal_price'] = $subtotal;
        $order['discount_amount'] = $discount;
        $order['total_price'] = max(0, $subtotal - $discount);
        $data['order'] = $order;
    }

    public function proceedToPayment(array $options = []): Payment
    {
        $this->validate();

        try {
            DB::beginTransaction();

            $data = $this->getData();
            $order = $this->createOrder($data);
            $full_name = $order->full_name;
            $email = $order->email;
            $phone_number = $order->phone_number;

            if ($order->user) {
                $full_name = $order->user->full_name;
                $email = $order->user->email;
                $phone_number = $order->user->phone_number;
            }

            $payment = Payment::create([
                'user_id' => $order->user_id,
                'full_name' => $full_name,
                'email' => $email,
                'phone_number' => $phone_number,
                'payment_gateway_id' => data_get($data, 'payment_gateway_id'),
                'amount' => $order->total_price,
                'currency' => 'NGN',
                'description' => 'Payment for order  '.$order->reference,
                'callback_url' => data_get($options, 'callback_url'),
            ]);

            $payment->payables()->create([
                'payable_type' => Order::class,
                'payable_id' => $order->id,
                'amount' => $order->total_price,
            ]);

            DB::commit();

            Cart::clear();
            $this->session->delete();

            return $payment;

        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }
    }

    protected function createOrder(array $data): Order
    {

        $order = new Order($data['order']);

        if ($user = Auth::user() ?? Auth::guard('sanctum')->user()) {
            $order->user_id = $user->id;
            $order->full_name = $user->full_name;
            $order->email = $user->email;
            $order->phone_number = $user->phone_number;
        } else {
            $order->full_name = data_get($data, 'delivery_address.full_name', 'Guest User');
            $order->email = data_get($data, 'delivery_address.email');
            $order->phone_number = data_get($data, 'delivery_address.phone_number');
        }
        $order->store_id = current_store()?->id;
        $order->coupon_id = $data['coupon_id'] ?? null;
        $order->delivery_address = $data['delivery_address'] ?? null;
        $order->save();

        foreach ($data['items'] ?? [] as $item) {
            $orderItem = new OrderItem($item);
            $orderItem->order_id = $order->id;
            $orderItem->options = $item['options'] ?? [];
            $orderItem->save();
        }

        return $order;
    }

    public function getSummary(): array
    {
        return array_merge($this->getData(), ['checkout_token' => $this->token()]);
    }

    /**
     * Validate checkout data before proceeding to payment
     *
     * @throws CheckoutValidationException
     */
    private function validate(): void
    {
        $data = $this->getData();
        $errors = [];

        // Validate cart items
        $itemErrors = $this->validateItems($data);
        if (!empty($itemErrors)) {
            $errors['items'] = $itemErrors;
        }

        // Validate billing address
        $billingErrors = $this->validateBillingAddress($data);
        if (!empty($billingErrors)) {
            $errors['billing_address'] = $billingErrors;
        }

        // Validate delivery address
        $deliveryErrors = $this->validateDeliveryAddress($data);
        if (!empty($deliveryErrors)) {
            $errors['delivery_address'] = $deliveryErrors;
        }

        // Validate payment gateway
        $gatewayErrors = $this->validatePaymentGateway($data);
        if (!empty($gatewayErrors)) {
            $errors['payment_gateway_id'] = $gatewayErrors;
        }

        // Validate order totals
        $amountErrors = $this->validateOrderAmount($data);
        if (!empty($amountErrors)) {
            $errors['amount'] = $amountErrors;
        }

        // If there are validation errors, throw exception
        if (!empty($errors)) {
            throw new CheckoutValidationException(
                $errors,
                'Please complete all required information before proceeding to payment.'
            );
        }
    }

    /**
     * Validate cart items
     */
    private function validateItems(array $data): array
    {
        $errors = [];

        if (empty($data['items']) || !is_array($data['items'])) {
            $errors[] = 'Your cart is empty. Please add items before proceeding to checkout.';
            return $errors;
        }

        if (count($data['items']) === 0) {
            $errors[] = 'Your cart is empty. Please add items before proceeding to checkout.';
            return $errors;
        }

        foreach ($data['items'] as $index => $item) {
            $itemPosition = $index + 1;

            if (empty($item['options']['itemable_type']) || empty($item['options']['itemable_id'])) {
                $errors[] = "Item #{$itemPosition}: Product information is missing.";
            }

            if (empty($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1) {
                $errors[] = "Item #{$itemPosition}: Invalid quantity.";
            }

            if (!isset($item['price']) || !is_numeric($item['price']) || $item['price'] < 0) {
                $errors[] = "Item #{$itemPosition}: Invalid price.";
            }

            if (empty($item['name'])) {
                $errors[] = "Item #{$itemPosition}: Product name is missing.";
            }
        }

        return $errors;
    }

    /**
     * Validate billing address
     */
    private function validateBillingAddress(array $data): array
    {
        $errors = [];

        if (empty($data['billing_address'])) {
            $errors[] = 'Billing address is required. Please provide your billing information.';
            return [];
        }

        $billing = $data['billing_address'];
        $requiredFields = [
            'full_name' => 'Full name',
            'phone_number' => 'Phone number',
            'email' => 'Email address',
            'address' => 'Street address',
            'country' => 'Country',
        ];

        foreach ($requiredFields as $field => $label) {
            // Check for nested country/state/city objects
            $value = $this->getNestedValue($billing, $field);

            if (empty($value)) {
                $errors[] = "{$label} is required in billing address.";
            }
        }

        // Validate email format
        if (!empty($billing['email']) && !filter_var($billing['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format in billing address.";
        }

        // Validate phone number format
        if (!empty($billing['phone_number']) && !preg_match('/^[\d\s\+\-\(\)]+$/', $billing['phone_number'])) {
            $errors[] = "Invalid phone number format in billing address.";
        }

        return [];
    }

    /**
     * Validate delivery address
     */
    private function validateDeliveryAddress(array $data): array
    {
        $errors = [];

        if (empty($data['delivery_address'])) {
            $errors[] = 'Delivery address is required. Please provide your delivery information.';
            return $errors;
        }

        $delivery = $data['delivery_address'];
        $requiredFields = [
            'full_name' => 'Full name',
            'phone_number' => 'Phone number',
            'email' => 'Email address',
            'address' => 'Street address',
            'state' => 'State',
            'country' => 'Country',
        ];

        foreach ($requiredFields as $field => $label) {
            // Check for nested country/state/city objects
            $value = $this->getNestedValue($delivery, $field);

            if (empty($value)) {
                $errors[] = "{$label} is required in delivery address.";
            }
        }

        // Validate email format
        if (!empty($delivery['email']) && !filter_var($delivery['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format in delivery address.";
        }

        // Validate phone number format
        if (!empty($delivery['phone_number']) && !preg_match('/^[\d\s\+\-\(\)]+$/', $delivery['phone_number'])) {
            $errors[] = "Invalid phone number format in delivery address.";
        }

        return $errors;
    }

    /**
     * Validate payment gateway
     */
    private function validatePaymentGateway(array $data): array
    {
        $errors = [];

        if (empty($data['payment_gateway_id'])) {
            $errors[] = 'Please select a payment method to continue.';
            return $errors;
        }

        // Check if gateway exists and is enabled
        $gateway = PaymentGateway::find($data['payment_gateway_id']);

        if (!$gateway) {
            $errors[] = 'Selected payment method does not exist. Please choose another payment option.';
            return $errors;
        }

        if ($gateway->is_disabled) {
            $errors[] = 'Selected payment method is currently unavailable. Please choose another payment option.';
            return $errors;
        }

        // Check if gateway has active configuration for current mode
        $mode = $gateway->mode;
        $config = $gateway->configs()->where('mode', $mode)->first();

        if (!$config) {
            $errors[] = 'Payment method is not properly configured. Please contact support or choose another payment option.';
        }

        return $errors;
    }

    /**
     * Validate order amount
     */
    private function validateOrderAmount(array $data): array
    {
        $errors = [];

        $order = $data['order'] ?? [];
        $amount = $order['total_price'] ?? 0;

        if ($amount <= 0) {
            $errors[] = 'Order total must be greater than zero.';
            return $errors;
        }

        // Validate minimum amount
        $minAmount = config('payment.minimum_amount', 100);
        if ($amount < $minAmount) {
            $errors[] = "Order total must be at least ₦".number_format($minAmount, 2).".";
        }

        // Validate maximum amount
        $maxAmount = config('payment.maximum_amount', 10000000);
        if ($amount > $maxAmount) {
            $errors[] = "Order total cannot exceed ₦".number_format($maxAmount, 2).".";
        }

        return $errors;
    }

    /**
     * Get nested value from address (handles both direct fields and nested objects)
     */
    private function getNestedValue(array $address, string $field): mixed
    {
        // Direct field access
        if (isset($address[$field]) && !empty($address[$field])) {
            // If it's an array (nested object like country/state/city), check for name
            if (is_array($address[$field])) {
                return $address[$field]['name'] ?? $address[$field]['id'] ?? null;
            }
            return $address[$field];
        }

        return null;
    }
}
