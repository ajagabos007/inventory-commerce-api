<?php

namespace App\Gateways;

use App\Interfaces\Payable;
use App\Models\Payment;
use App\Exceptions\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveGateway implements Payable {

    protected array $config;
    protected string $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function initialize(Payment $payment): array {
        $url = "{$this->baseUrl}/payments";

        $payload = [
            'tx_ref' => $payment->transaction_reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency ?? 'NGN',
            'redirect_url' => $payment->callback_url ?? route('payment.callback'),
            'customer' => [
                'email' => $payment->user->email,
                'name' => $payment->user->full_name ?? $payment->user->first_name,
            ],
            'customizations' => [
                'title' => $payment->description ?? 'Payment',
            ],
            'meta' => [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
            ],
        ];

        $response = Http::withToken($this->config['credentials']['secret_key'])
//            ->timeout($this->config['settings']['timeout'] ?? 30)
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::error('Flutterwave initialization failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payment_id' => $payment->id,
            ]);

            throw new PaymentException(
                "Flutterwave initialization failed: " . ($response->json()['message'] ?? 'Unknown error'),
                $response->status()
            );
        }

        $data = $response->json()['data'];

        return [
            'gateway_reference' => $data['tx_ref'] ?? $payment->transaction_reference,
            'checkout_url' => $data['link'],
        ];
    }

    public function verifyWebhook(array $webhookData, Payment $payment): array {
        $transactionId = $webhookData['data']['id'] ?? null;

        if (!$transactionId) {
            throw new PaymentException("Invalid webhook data: missing transaction ID", 400);
        }

        // Verify transaction with Flutterwave API
        $url = "{$this->baseUrl}/transactions/{$transactionId}/verify";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->get($url);

        if (!$response->successful()) {
            throw new PaymentException("Transaction verification failed", $response->status());
        }

        $data = $response->json()['data'];

        return [
            'status' => $data['status'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'method' => $data['payment_type'] ?? null,
            'reference' => $data['tx_ref'],
            'paid_at' => $data['created_at'] ?? now(),
        ];
    }

    public function validateWebhookSignature(array $headers, string $payload): bool {
        $signature = $headers['verif-hash'] ?? null;
        $secretHash = $this->config['credentials']['secret_hash'] ?? $this->config['credentials']['secret_key'];

        if (!$signature) {
            return false;
        }

        return hash_equals($secretHash, $signature);
    }
}
