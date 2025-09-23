<?php

namespace App\Http\Requests;

use App\Enums\AttributeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->attribute);
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
                'sometimes',
                'required',
                'string',
                'max:191',
                Rule::unique('attributes', 'name')
                    ->ignore($this->attribute),
            ],
            'type' => ['nullable', Rule::in(AttributeType::values(), 'i')],
        ];
    }
}
