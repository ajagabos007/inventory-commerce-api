<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleAddInventoryRequest extends FormRequest
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
            'inventory_id' => 'required|exists:inventory,id',
            'quantity' => 'required|integer|min:1',
            'price_per_gram' => 'nullable|numeric|min:0.01',
        ];
    }
}
