<?php

namespace App\Gateways;

use App\Interfaces\Payable;
use App\Models\Payment;
use App\Exceptions\PaymentException;
use Illuminate\Http\Client\ConnectionException;
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
            'redirect_url' => $payment->callback_url ?? route('api.payment.callback','flutterwave'),
            'customer' => [
                'email' => $payment->user?->email ?? $payment->email,
                'phone_number' => $payment->user?->phone_number ?? $payment->phone_number,
                'name' => $payment->user?->full_name ?? $payment->full_name,
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
            ->timeout($this->config['settings']['timeout'] ?? 30)
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

    public function verify(Payment $payment): array
    {
        return $this->verifyReference($payment->gateway_reference);
    }

    public function verifyWebhook(array $webhookData, Payment $payment): array {
        $transactionId = $webhookData['data']['id'] ?? null;

        if (!$transactionId) {
            throw new PaymentException("Invalid webhook data: missing transaction ID", 400);
        }

        return  $this->verifyTransaction($transactionId);
    }

    public function validateWebhookSignature(array $headers, string $payload): bool {
        $signature = $headers['verif-hash'][0] ?? null;
        $secretHash = $this->config['credentials']['secret_hash'] ?? $this->config['credentials']['encryption_key'] ?? null;

        if (!$signature) {
            return false;
        }

        return hash_equals($secretHash, $signature);
    }

    public function verifyCallback(array $query, Payment $payment): array
    {
        $transactionId = data_get($query, 'transaction_id', null);

        if (!$transactionId) {
            throw new PaymentException("Invalid callback data: missing transaction id", 400);
        }

        return  $this->verifyTransaction($transactionId);
    }

    /**
     * @param string $transactionId
     * @return array
     * @throws ConnectionException
     * @throws PaymentException
     */
    public function verifyTransaction(string $transactionId): array {

        $url = "{$this->baseUrl}/transactions/{$transactionId}/verify";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->get($url);

        if (!$response->successful()) {
            throw new PaymentException("Transaction verification failed", $response->status());
        }

        return $this->extracted($response);
    }

    public function verifyReference(string $reference): array {
        $url = "{$this->baseUrl}/transactions/verify_by_reference?tx_ref={$reference}";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->get($url);

        if (!$response->successful()) {
            throw new PaymentException("Reference verification failed", $response->status());
        }

        return $this->extracted($response);
    }

    /**
     * @param \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response $response
     * @return array
     */
    public function extracted(\GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response $response): array
    {
        $data = $response->json()['data'];

        return [
            'status' => $data['status'],
            'transaction_status' => $data['status'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'method' => $data['payment_type'] ?? null,
            'reference' => $data['tx_ref'],
            'paid_at' => $data['created_at'] ?? now(),
            'verified_at' => now(),
            'ip_address' => $data['ip'] ?? null,
            'metadata' => [
                'transaction' => $data,
            ],
        ];
    }
}
