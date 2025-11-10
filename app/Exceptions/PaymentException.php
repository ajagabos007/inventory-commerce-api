<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class PaymentException extends Exception
{
    private int $statusCode;

    public function __construct(
        string $message = 'Payment error occurred',
        int $statusCode = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Report the exception
     */
    public function report(): void
    {
        Log::error('Payment Exception', [
            'message' => $this->getMessage(),
            'status_code' => $this->statusCode,
            'trace' => $this->getTraceAsString(),
        ]);
    }

    /**
     * Render the exception as an HTTP response
     */
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->statusCode,
        ], $this->statusCode);
    }
}
