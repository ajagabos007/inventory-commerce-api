<?php

namespace App\Http\Requests;

use App\Models\AttributeValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeValueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', AttributeValue::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'attribute_id' => ['required', 'exists:attributes,id'],
            'value' => ['required', 'string', 'max:1000',
                Rule::unique('attribute_values', 'value')->where(function ($query) {
                    $query->where('attribute_id', $this->input('attribute_id'));
                }),
            ],
            'display_value' => ['nullable', 'string', 'max:1000'],
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
            'attribute_id' => 'attribute',
        ];
    }
}
