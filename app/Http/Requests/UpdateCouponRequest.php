<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->coupon);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed,string>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'sometimes|required|string|max:50|unique:coupons,code,' . $this->coupon,
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:percentage,fixed',
            'value' => 'sometimes|required|numeric|min:0',
            'minimum_order_amount' => 'nullable|numeric|min:0',
            'maximum_discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'sometimes|required|integer|min:1',
            'is_active' => 'boolean',
            'valid_from' => 'sometimes|required|date',
            'valid_until' => 'sometimes|required|date|after:valid_from',
        ];
    }
}
