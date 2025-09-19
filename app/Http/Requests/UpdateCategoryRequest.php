<?php

namespace App\Http\Requests;

use App\Rules\Base64File;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->category);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [

            'name' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('categories', 'name')->ignore($this->category),
            ],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'image' => [
                'nullable',
                new Base64File($allowed_mimetypes = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'], $allowed_extensions = [], $max_size_kb = 2048),
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
            'parent_id' => 'parent category',
        ];
    }
}
