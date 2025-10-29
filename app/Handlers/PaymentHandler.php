<?php

namespace App\Handlers;

use App\Gateways\PaystackGateway;
use App\Gateways\FlutterwaveGateway;
use App\Interfaces\Payable;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayConfig;
use App\Exceptions\PaymentException;

class PaymentHandler {

    private ?Payable $gateway = null;
    private Payment $payment;
    private PaymentGateway $paymentGateway;
    private PaymentGatewayConfig $gatewayConfig;
    private array $config;

    public function __construct(Payment $payment, ?string $mode = null) {
        $this->payment = $payment;
        $this->validatePayment();
        $this->loadGatewayConfig($mode);
        $this->gateway = $this->initializeGateway();
    }

    private function validatePayment(): void {
        if (!$this->payment || !$this->payment->exists) {
            throw new PaymentException("Invalid or non-existent payment", 404);
        }

        if (!$this->payment->gateway) {
            throw new PaymentException("Payment gateway not associated with this payment", 400);
        }

        $this->paymentGateway = $this->payment->gateway;

        if ($this->paymentGateway->is_disabled) {
            throw new PaymentException(
                "Payment gateway '{$this->paymentGateway->name}' is currently disabled",
                403
            );
        }
    }

    private function loadGatewayConfig(?string $mode = null): void {
        if (!$mode) {
            $mode = config('payment.default_mode', 'sandbox');
        }

        if (!in_array($mode, ['sandbox', 'live'])) {
            throw new PaymentException("Invalid payment mode: {$mode}", 400);
        }

        $this->gatewayConfig = $this->paymentGateway->configs()
            ->where('mode', $mode)
            ->enabled()
            ->first();

        if (!$this->gatewayConfig) {
            throw new PaymentException(
                "No active configuration found for '{$this->paymentGateway->name}' in {$mode} mode",
                500
            );
        }

        $credentials = $this->gatewayConfig->decrypted_credentials;
        $settings = $this->gatewayConfig->decrypted_settings;

        if (empty($credentials)) {
            throw new PaymentException(
                "No credentials configured for {$mode} mode",
                500
            );
        }

        $this->config = [
            'mode' => $mode,
            'gateway_id' => $this->paymentGateway->id,
            'gateway_name' => $this->paymentGateway->name,
            'gateway_code' => $this->paymentGateway->code,
            'config_id' => $this->gatewayConfig->id,
            'credentials' => $credentials,
            'settings' => $settings,
            'supported_currencies' => $this->paymentGateway->supported_currencies ?? [],
        ];

        $this->validateCredentials();
    }

    private function validateCredentials(): void {
        $gatewayCode = strtolower($this->paymentGateway->code);
        $credentials = $this->config['credentials'];

        $requiredFields = match($gatewayCode) {
            'paystack' => ['public_key', 'secret_key'],
            'flutterwave' => ['public_key', 'secret_key', 'encryption_key'],
            default => [],
        };

        $missingFields = array_filter($requiredFields, fn($field) => empty($credentials[$field]));

        if (!empty($missingFields)) {
            throw new PaymentException(
                "Missing required credentials: " . implode(', ', $missingFields),
                500
            );
        }
    }

    private function initializeGateway(): Payable {
        $gatewayCode = strtolower(trim($this->paymentGateway->code));

        return match($gatewayCode) {
            'flutterwave' => new FlutterwaveGateway($this->config),
            'paystack' => new PaystackGateway($this->config),
            default => throw new PaymentException("Unsupported payment gateway: {$gatewayCode}", 400),
        };
    }

    public function getGateway(): Payable {
        return $this->gateway;
    }

    public function getMode(): string {
        return $this->config['mode'];
    }

    public function isSandbox(): bool {
        return $this->config['mode'] === 'sandbox';
    }

    /**
     * Initialize payment and get checkout URL for React frontend
     */
    public function initializePayment(): array {
        try {
            // Generate transaction reference if not set
            if (!$this->payment->transaction_reference) {
                $this->payment->transaction_reference = $this->generateReference();
                $this->payment->save();
            }

            // Initialize payment with gateway
            $response = $this->gateway->initialize($this->payment);

            // Update payment with gateway response
            $this->payment->update([
                'gateway_reference' => $response['gateway_reference'] ?? null,
                'checkout_url' => $response['checkout_url'] ?? null,
                'status' => 'pending',
            ]);

            return [
                'success' => true,
                'message' => 'Payment initialized successfully',
                'data' => [
                    'payment_id' => $this->payment->id,
                    'transaction_reference' => $this->payment->transaction_reference,
                    'gateway_reference' => $this->payment->gateway_reference,
                    'checkout_url' => $this->payment->checkout_url,
                    'amount' => $this->payment->amount,
                    'currency' => $this->payment->currency,
                    'mode' => $this->config['mode'],
                ],
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentException("Payment initialization failed: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Verify payment from webhook
     */
    public function verifyFromWebhook(array $webhookData): array {
        try {
            $verificationResult = $this->gateway->verifyWebhook($webhookData, $this->payment);

            // Update payment based on verification
            $this->updatePaymentFromVerification($verificationResult);

            return [
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => [
                    'payment_id' => $this->payment->id,
                    'status' => $this->payment->status,
                    'transaction_status' => $this->payment->transaction_status,
                ],
            ];

        } catch (\Exception $e) {
            throw new PaymentException("Payment verification failed: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Update payment record from verification result
     */
    private function updatePaymentFromVerification(array $result): void {
        $updateData = [
            'transaction_status' => $result['status'],
            'method' => $result['method'] ?? null,
        ];

        if ($result['status'] === 'success' || $result['status'] === 'successful') {
            $updateData['status'] = 'completed';
            $updateData['paid_at'] = now();
            $updateData['verified_at'] = now();
        } elseif ($result['status'] === 'failed') {
            $updateData['status'] = 'failed';
        }

        $this->payment->update($updateData);
    }

    /**
     * Generate unique transaction reference
     */
    private function generateReference(): string {
        return strtoupper(uniqid('TXN_' . date('YmdHis') . '_'));
    }
}
