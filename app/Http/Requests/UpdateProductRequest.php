<?php

namespace App\Http\Requests;

use App\Rules\Base64File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->item);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'category_id' => ['sometimes', 'requiredIf:material,'.Material::GOLD->value, 'exists:categories,id'],
            'colour_id' => ['sometimes', 'requiredIf:material,'.Material::GOLD->value, 'exists:colours,id'],
            'type_id' => ['sometimes', 'required', 'exists:types,id'],
            'weight' => ['requiredIf:material,'.Material::GOLD->value, 'numeric'],
            'price' => [Rule::excludeIf($this->input('material') == Material::GOLD->value), 'numeric', 'min:1'],
            'quantity' => ['integer', 'min:1'],
            'upload_image' => [
                'sometimes', 'required', 'string',
                new Base64File($allowed_mimetypes = [
                    'image/jpeg',
                    'image/png',
                    'image/svg+xml',
                    'image/webp',
                ], $allowed_extensions = [], $max_size_kb = 2048),
            ],
            // 'images' => ['sometimes','required','array'],
            // 'images.*' => [
            //     'sometimes','required','string',
            //     new Base64File($allowed_mimetypes=['image/jpeg', 'image/png', 'image/svg+xml'],$allowed_extensions=[], $max_size_kb=2048)
            //  ]
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
