<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use App\Rules\Base64File;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', ProductVariant::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'name' => ['required', 'string', 'max:191'],
            'price' => ['required', 'numeric', 'min:1'],
            'compare_price' => ['nullable', 'numeric', 'min:1'],
            'cost_price' => ['nullable', 'numeric', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'attribute_value_ids' => ['required', 'array', 'min:1'],
            'attribute_value_ids.*' => ['required', 'exists:attribute_values,id'],
            'is_serialized' => ['boolean'],
            'serial_number' => ['required_if:is_serialized,true'],
            'batch_number' => ['nullable'],
            'images' => ['required', 'array', 'min:1', 'max:4'],
            'images*' => [
                'required',
                'string',
                new Base64File($allowed_mimetypes = [
                    'image/jpeg',
                    'image/png',
                    'image/svg+xml',
                    'image/webp',
                ], $allowed_extensions = [], $max_size_kb = 2048),
            ],
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
            'product_id' => 'product',
            'attribute_value_ids.*' => 'attribute value [:position]',
        ];
    }
}
