<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class CheckoutValidationException extends Exception
{
    protected array $errors;
    protected int $statusCode = 422;

    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Render the exception as HTTP response
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ], $this->statusCode);
    }

    /**
     * Report the exception (optional logging)
     */
    public function report(): void
    {
        \Log::warning('Checkout validation failed', [
            'errors' => $this->errors,
            'user_id' => auth()->id(),
            'session' => request()->header('x-session-token'),
        ]);
    }
}
