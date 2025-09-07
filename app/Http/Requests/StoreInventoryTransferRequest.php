<?php

namespace App\Http\Requests;

use App\Models\Inventory;
use App\Models\InventoryTransfer;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryTransfer::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_id' => ['required', Rule::exists(Inventory::class, 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'driver_name' => ['required', 'string', 'max:255'],
            'driver_phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'store_id' => ['required', Rule::exists(Store::class, 'id')],
        ];
    }
}
