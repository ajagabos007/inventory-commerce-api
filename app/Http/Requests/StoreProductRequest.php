<?php

namespace App\Http\Requests;

use App\Enums\Material;
use App\Models\Product;
use App\Rules\Base64File;
use App\Rules\In;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'material' => [
                'required', 'string',
                new In(Material::values(), $caseSensitive = true),
                Rule::unique('products', 'material')
                    ->where('category_id', $this->input('category_id'))
                    ->where('colour_id', $this->input('colour_id'))
                    ->where('type_id', $this->input('type_id'))
                    ->where('weight', $this->input('weight')),
            ],
            'sku' => ['nullable', 'string', 'max:191', 'unique:products,sku'],
            'category_id' => ['requiredIf:material,'.Material::GOLD->value, 'exists:categories,id'],
            'colour_id' => ['requiredIf:material,'.Material::GOLD->value, 'exists:colours,id'],
            'type_id' => ['required', 'exists:types,id'],
            'weight' => ['requiredIf:material,'.Material::GOLD->value, 'numeric'],
            'price' => [Rule::excludeIf($this->input('material') == Material::GOLD->value), 'numeric', 'min:1'],
            'quantity' => ['integer', 'min:1'],
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
            'category_id' => 'category',
            'colour_id' => 'colour',
            'type_id' => 'type',
            'upload_image' => 'image',
            'material' => 'commodity',
        ];
    }
}
