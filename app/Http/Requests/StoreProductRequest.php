<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Rules\Base64File;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191', 'unique:products,name'],
            'short_description' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:1'],
            'compare_price' => ['nullable', 'numeric', 'min:1'],
            'cost_price' => ['nullable', 'numeric', 'min:1'],
            'quantity' => ['integer', 'min:1'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['nullable', 'exists:attribute_values,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['nullable', 'exists:categories,id'],
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
            'is_serialized' => ['boolean'],
            'serial_number' => ['required_if:is_serialized,true', 'unique:inventories,serial_number'],
            'batch_number' => ['nullable'],

            'variants' => ['nullable', 'array'],
            'variants.*.name' => ['required', 'string', 'max:191'],
            'variants.*.price' => ['required', 'numeric', 'min:1'],
            'variants.*.compare_price' => ['nullable', 'numeric', 'min:1'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:1'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:1'],
            'variants.*.attribute_value_ids' => ['required', 'array', 'min:1'],
            'variants.*.attribute_value_ids.*' => ['required', 'exists:attribute_values,id'],
            'variants.*.is_serialized' => ['boolean'],
            'variants.*.serial_number' => ['required_if:variants.*.is_serialized,true'],
            'variants.*.batch_number' => ['nullable'],
            'variants.*.images' => ['required', 'array', 'min:1', 'max:4'],
            'variants.*.images*' => [
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
            'category_ids.*' => 'category [:position]',
            'attribute_value_ids.*' => 'attribute value [:position]',
        ];
    }
}
