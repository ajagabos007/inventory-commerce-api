<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use App\Rules\In;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
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
            'customer_id' => ['required', 'exists:customers,id'],
            'tax' => ['numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', 'string',   new In(PaymentMethod::values(), $caseSensitive = false)],

            'sale_inventories' => 'required|array|min:1',
            'sale_inventories.*.inventory_id' => 'required|exists:inventories,id',
            'sale_inventories.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {},
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
        ];
    }
}
