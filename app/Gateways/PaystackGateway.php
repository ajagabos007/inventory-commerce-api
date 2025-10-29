<?php
namespace App\Gateways;

use App\Interfaces\Payable;
use App\Models\Payment;
use App\Exceptions\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackGateway implements Payable {

    protected array $config;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function initialize(Payment $payment): array {
        $url = "{$this->baseUrl}/transaction/initialize";

        $payload = [
            'email' => $payment->user->email,
            'amount' => $payment->amount * 100, // Convert to kobo
            'reference' => $payment->transaction_reference,
            'currency' => $payment->currency ?? 'NGN',
            'callback_url' => $payment->callback_url ?? route('payment.callback'),
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'description' => $payment->description,
            ],
        ];

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->timeout($this->config['settings']['timeout'] ?? 30)
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Paystack initialization failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payment_id' => $payment->id,
            ]);

            throw new PaymentException(
                "Paystack initialization failed: " . ($response->json()['message'] ?? 'Unknown error'),
                $response->status()
            );
        }

        $data = $response->json()['data'];

        return [
            'gateway_reference' => $data['reference'],
            'checkout_url' => $data['authorization_url'],
            'access_code' => $data['access_code'],
        ];
    }

    public function verifyWebhook(array $webhookData, Payment $payment): array {
        $reference = $webhookData['data']['reference'] ?? null;

        if (!$reference) {
            throw new PaymentException("Invalid webhook data: missing reference", 400);
        }

        // Verify transaction with Paystack API
        $url = "{$this->baseUrl}/transaction/verify/{$reference}";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->get($url);

        if (!$response->successful()) {
            throw new PaymentException("Transaction verification failed", $response->status());
        }

        $data = $response->json()['data'];

        return [
            'status' => $data['status'],
            'amount' => $data['amount'] / 100,
            'currency' => $data['currency'],
            'method' => $data['channel'] ?? null,
            'reference' => $data['reference'],
            'paid_at' => $data['paid_at'] ?? now(),
        ];
    }

    public function validateWebhookSignature(array $headers, string $payload): bool {
        $signature = $headers['x-paystack-signature'] ?? null;

        if (!$signature) {
            return false;
        }

        $hash = hash_hmac('sha512', $payload, $this->config['credentials']['secret_key']);

        return hash_equals($hash, $signature);
    }
}
