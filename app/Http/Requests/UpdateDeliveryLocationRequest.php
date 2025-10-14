<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->delivery_location);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<string,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['sometimes', 'required', 'exists:stores,id'],
            'country_id' => ['sometimes', 'required', 'exists:countries,id'],
            'state_id' => ['sometimes', 'required', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'delivery_fee' => ['sometimes', 'required', 'numeric', 'min:0'],
            'estimated_delivery_days' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id' => 'store',
            'country_id' => 'country',
            'state_id' => 'state',
            'city_id' => 'city',
        ];
    }
}
