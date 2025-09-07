<?php

namespace App\Http\Requests;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Discount::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'unique:discounts,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'is_active' => ['boolean'],
        ];
    }
}
