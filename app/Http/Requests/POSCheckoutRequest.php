<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Rules\In;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_code' => ['nullable', 'exists:discounts,code', function ($attribute, $value, $fail) {
                if ($value) {
                    $discount = \App\Models\Discount::where('code', $value)->first();
                    if (! $discount) {
                        return;
                    }

                    if (! $discount->is_active) {
                        $fail('The selected discount code is not active.');
                    } elseif ($discount->expires_at && \Carbon\Carbon::parse($discount->expires_at)->isPast()) {
                        $fail('The selected discount code has expired.');
                    }
                }
            }],
            'customer_user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone_number' => ['nullable', 'string', 'max:20'],
            'tax' => ['numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', 'string',   new In(PaymentMethod::values(), $caseSensitive = false)],
        ];
    }
}
