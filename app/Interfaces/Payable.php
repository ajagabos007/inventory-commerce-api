<?php

namespace App\Interfaces;

use App\Models\Payment;

interface Payable {

    public function __construct(array $config);

    /**
     * Initialize payment and return checkout URL
     */
    public function initialize(Payment $payment): array;

    /**
     * Verify payment from webhook data
     */
    public function verifyWebhook(array $webhookData, Payment $payment): array;

    /**
     * Validate webhook signature
     */
    public function validateWebhookSignature(array $headers, string $payload): bool;
}
