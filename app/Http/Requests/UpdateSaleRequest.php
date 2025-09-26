<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Rules\In;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->sale);
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
                    $discount = \App\Models\Discount::where('code', $value)
                        ->where('id', '<>', $this->sale->discount_id)
                        ->first();
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
            'discount_id' => ['sometimes', 'nullable', 'exists:discounts,id'],
            'customer_id' => ['sometimes', 'required', 'exists:customers,id'],
            'tax' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'payment_method' => ['sometimes', 'required', 'string', new In(PaymentMethod::values(), $caseSensitive = false)],
            'sale_inventories' => ['sometimes', 'required', 'array', 'min:1'],
            'sale_inventories.*.id' => ['nullable', 'exists:sale_inventories,id'],
            'sale_inventories.*.inventory_id' => ['required_with:sale_inventories', 'exists:inventories,id'],
            'sale_inventories.*.quantity' => ['required_with:sale_inventories', 'integer', 'min:1'],
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
