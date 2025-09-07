<?php

namespace App\Http\Requests;

use App\Models\Colour;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreColourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Colour::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:191',
                Rule::unique('colours', 'name')
                    ->where('hex', $this->input('hex')),
            ],
            'hex' => ['nullable', 'hex_color'],
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

        ];
    }
}
