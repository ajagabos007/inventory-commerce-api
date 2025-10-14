<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use App\Rules\Base64File;
use App\Rules\In;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->payment_gateway);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<string,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'logo' => [
                'sometimes',
                'required',
                new Base64File($allowed_mimetypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'], $allowed_extensions = [], $max_size_kb = 2048),
            ],
            'is_disabled' => ['nullable', 'boolean'],
            'mode' => ['string', new In(PaymentMode::values())],
        ];
    }
}
