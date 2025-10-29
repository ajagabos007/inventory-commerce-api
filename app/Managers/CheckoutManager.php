<?php

namespace App\Managers;

use App\Facades\Cart;
use App\Models\CheckoutSession;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentGateway;
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

        $user = auth()->user();

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


    public function proceedToPayment(array $options=[]): Payment
    {
        $this->validate();

        try {
            DB::beginTransaction();

            $data = $this->getData();
            $order = $this->createOrder($data);
            $full_name = $order->full_name;
            $email = $order->email;
            $phone_number = $order->phone_number;

            if($order->user){
                $full_name = $order->user->full_name;
                $email = $order->user->email;
                $phone_number = $order->user->phone_number;
            }

            $payment =  Payment::create([
                'user_id' => $order->user_id,
                'full_name' => $full_name,
                'email' => $email,
                'phone_number' => $phone_number,
                'payment_gateway_id' => data_get($data, 'payment_gateway_id'),
                'amount'  => $order->total_price,
                'currency' => 'NGN',
                'description' => 'Payment for Order #' . $order->id,
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

    private function validate():void
    {
        $data = $this->getData();

        if (empty($data['billing_address'])) {
            throw new \Exception('Billing address is not set.');
        }

        if (empty($data['delivery_address'])) {
            throw new \Exception('Delivery address is not set.');
        }else {
            $delivery = $data['delivery_address'];
            if (empty($delivery['full_name']) || empty($delivery['phone_number']) || empty($delivery['email']) || empty($delivery['address']) || empty($delivery['state']) || empty($delivery['country'])) {
                throw new \Exception('Delivery address is incomplete.');
            }
        }

        if (empty($data['payment_gateway_id'])) {
            throw new \Exception('Payment gateway is not set.');
        }

        if (empty($data['items'])) {
            throw new \Exception('No items in the cart.');
        }
    }
}
