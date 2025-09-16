<?php

namespace App\Http\Requests;

use App\Models\AttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncAttributeValuesRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => [
                'nullable',
                Rule::exists(AttributeValue::class, 'id'),
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
            'attribute_value_ids' => 'attribute values',
            'attribute_value_ids.*' => 'attribute value [:position]',
        ];
    }
}
