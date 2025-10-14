<?php

namespace App\Http\Requests;

use App\Models\DeliveryLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', DeliveryLocation::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<string,mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'exists:stores,id'],
            'country_id' => ['required', 'exists:countries,id'],
            'state_id' => ['required', 'exists:states,id'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'estimated_delivery_days' => ['required', 'integer', 'min:0'],
        ];
    }

    public function prepareForValidation(){

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
