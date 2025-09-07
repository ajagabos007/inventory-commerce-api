<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->inventory_transfer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_id' => ['sometimes', 'required', 'exists:inventories,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'driver_name' => ['sometimes', 'required', 'string', 'max:255'],
            'driver_phone_number' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'status' => ['sometimes', 'required', 'string', 'in:pending,approved,rejected'],
            'store_id' => ['sometimes', 'required', 'integer', 'exists:stores,id'],
        ];
    }
}
