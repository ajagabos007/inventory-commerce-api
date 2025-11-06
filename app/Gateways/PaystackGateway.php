<?php

namespace App\Gateways;

use App\Exceptions\PaymentException;
use App\Interfaces\Payable;
use App\Models\Payment;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackGateway implements Payable
{
    protected array $config;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Initialize payment with Paystack
     *
     * @throws PaymentException|ConnectionException
     */
    public function initialize(Payment $payment): array
    {
        $url = "{$this->baseUrl}/transaction/initialize";

        $payload = [
            'email' => $payment->user?->email ?? $payment->email,
            'amount' => $payment->amount * 100, // Convert to kobo (smallest currency unit)
            'reference' => $payment->transaction_reference,
            'currency' => $payment->currency ?? 'NGN',
            'callback_url' => $payment->callback_url ?? route('api.payment.callback', 'paystack'),
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
                'full_name' => $payment->user?->full_name ?? $payment->full_name,
                'phone_number' => $payment->user?->phone_number ?? $payment->phone_number,
                'description' => $payment->description,
            ],
        ];

        // Add optional fields if configured
        if (! empty($this->config['settings']['channels'])) {
            $payload['channels'] = $this->config['settings']['channels'];
        }

        if (! empty($this->config['settings']['split_code'])) {
            $payload['split_code'] = $this->config['settings']['split_code'];
        }

        if (! empty($this->config['settings']['subaccount'])) {
            $payload['subaccount'] = $this->config['settings']['subaccount'];
        }

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->timeout($this->config['settings']['timeout'] ?? 30)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::error('Paystack initialization failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payment_id' => $payment->id,
            ]);

            throw new PaymentException(
                'Paystack initialization failed: '.($response->json()['message'] ?? 'Unknown error'),
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

    /**
     * Verify payment by reference
     *
     * @throws PaymentException
     */
    public function verify(Payment $payment): array
    {
        return $this->verifyReference($payment->gateway_reference ?? $payment->transaction_reference);
    }

    /**
     * Verify payment from webhook data
     *
     * @throws PaymentException
     */
    public function verifyWebhook(array $webhookData, Payment $payment): array
    {
        $reference = $webhookData['data']['reference'] ?? null;

        if (! $reference) {
            throw new PaymentException('Invalid webhook data: missing reference', 400);
        }

        return $this->verifyReference($reference);
    }

    /**
     * Validate webhook signature from Paystack
     */
    public function validateWebhookSignature(array $headers, string $payload): bool
    {
        $signature = $headers['x-paystack-signature'][0] ?? null;

        if (! $signature) {
            return false;
        }

        $hash = hash_hmac('sha512', $payload, $this->config['credentials']['secret_key']);

        return hash_equals($hash, $signature);
    }

    /**
     * Verify payment from callback query parameters
     *
     * @throws PaymentException|ConnectionException
     */
    public function verifyCallback(array $query, Payment $payment): array
    {
        $reference = data_get($query, 'reference', null);

        if (! $reference) {
            throw new PaymentException('Invalid callback data: missing reference', 400);
        }

        return $this->verifyReference($reference);
    }

    /**
     * Verify transaction by reference
     *
     * @throws ConnectionException
     * @throws PaymentException
     */
    public function verifyReference(string $reference): array
    {
        $url = "{$this->baseUrl}/transaction/verify/{$reference}";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->timeout($this->config['settings']['timeout'] ?? 30)
            ->get($url);

        if (! $response->successful()) {
            Log::error('Paystack verification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'reference' => $reference,
            ]);

            throw new PaymentException(
                'Transaction verification failed: '.($response->json()['message'] ?? 'Unknown error'),
                $response->status()
            );
        }

        return $this->extractVerificationData($response);
    }

    /**
     * Extract and format verification data from Paystack response
     */
    protected function extractVerificationData(PromiseInterface|Response $response): array
    {
        $data = $response->json()['data'];

        return [
            'status' => $data['status'] === 'success' ? 'completed' : 'failed',
            'transaction_status' => $data['status'],
            'amount' => $data['amount'] / 100, // Convert from kobo back to main currency
            'currency' => $data['currency'],
            'method' => $data['channel'] ?? null,
            'reference' => $data['reference'],
            'gateway_reference' => $data['reference'],
            'paid_at' => $data['paid_at'] ?? ($data['paidAt'] ?? now()),
            'verified_at' => now(),
            'ip_address' => $data['ip_address'] ?? null,
            'metadata' => [
                'transaction' => $data,
                'fees' => $data['fees'] ?? null,
                'authorization' => $data['authorization'] ?? null,
                'customer' => $data['customer'] ?? null,
            ],
        ];
    }

    /**
     * Charge authorization (for recurring payments)
     *
     * @throws PaymentException
     */
    public function chargeAuthorization(Payment $payment, string $authorizationCode): array
    {
        $url = "{$this->baseUrl}/transaction/charge_authorization";

        $payload = [
            'authorization_code' => $authorizationCode,
            'email' => $payment->user?->email ?? $payment->email,
            'amount' => $payment->amount * 100,
            'currency' => $payment->currency ?? 'NGN',
            'reference' => $payment->transaction_reference,
            'metadata' => [
                'payment_id' => $payment->id,
                'user_id' => $payment->user_id,
            ],
        ];

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->timeout($this->config['settings']['timeout'] ?? 30)
            ->post($url, $payload);

        if (! $response->successful()) {
            Log::error('Paystack charge authorization failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payment_id' => $payment->id,
            ]);

            throw new PaymentException(
                'Charge authorization failed: '.($response->json()['message'] ?? 'Unknown error'),
                $response->status()
            );
        }

        return $this->extractVerificationData($response);
    }

    /**
     * Get transaction timeline
     *
     * @throws PaymentException|ConnectionException
     */
    public function getTransactionTimeline(string $reference): array
    {
        $url = "{$this->baseUrl}/transaction/timeline/{$reference}";

        $response = Http::withToken($this->config['credentials']['secret_key'])
            ->get($url);

        if (! $response->successful()) {
            throw new PaymentException('Failed to fetch transaction timeline', $response->status());
        }

        return $response->json()['data'];
    }

    /**
     * Check if transaction is pending
     */
    public function isTransactionPending(string $reference): bool
    {
        try {
            $data = $this->verifyReference($reference);

            return in_array($data['transaction_status'], ['pending', 'ongoing']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get transaction details
     *
     * @throws PaymentException|ConnectionException
     */
    public function getTransaction(string $reference): array
    {
        return $this->verifyReference($reference);
    }
}
