<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Rules\In;
use App\Rules\ValidDiscountCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class POSCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<string,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_code' => ['nullable', 'exists:discounts,code', new ValidDiscountCode],
            'customer_id' => ['required', 'exists:customers,id'],
            'tax' => ['numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', 'string',   new In(PaymentMethod::values(), $caseSensitive = false)],
        ];
    }
}
