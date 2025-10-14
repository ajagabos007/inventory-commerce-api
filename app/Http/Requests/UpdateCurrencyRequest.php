<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->currency);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'unique:currencies,code',
                Rule::unique('currencies', 'code')
                    ->ignore($this->currency),
            ],
            'symbol' => ['sometimes', 'required', 'string', 'max:10'],
            'exchange_rate' => ['sometimes', 'required', 'numeric', 'min:0.000001'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }
}
