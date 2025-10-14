<?php

namespace App\Http\Requests\ECommerce;

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
            'product_variant_id' => ['required_without:product_id', 'string'],
            'product_id' => ['exclude_with:product_variant_id', 'required_without:product_variant_id', 'string'],
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
                    $query->when(!blank($this->product_variant_id), function ($query) {
                        $query->where('id', $this->product_variant_id);
                    }, function ($query) {
                        $query->where('product_id', $this->product_id);
                    });
                })
                ->outOfStock(false)
                ->exists();

                if (! $inventoryExists) {
                    $validator->errors()->add('product_variant_id', 'Product does not exists or out of stock');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'product_variant_id' => 'Product variant',
            'product_id' => 'Product'
        ];
    }
}
