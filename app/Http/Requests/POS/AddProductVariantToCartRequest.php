<?php

namespace App\Http\Requests\POS;

use App\Models\Inventory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddProductVariantToCartRequest extends FormRequest
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
            'sku' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $inventoryExists = Inventory::whereHas('productVariant', function ($query) {
                    $query->where('sku', $this->sku);
                })->exists();
                if (! $inventoryExists) {
                    $validator->errors()->add('sku', 'Product SKU does not exists');
                }
            },
        ];
    }
}
