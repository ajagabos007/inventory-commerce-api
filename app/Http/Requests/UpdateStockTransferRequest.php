<?php

namespace App\Http\Requests;

use App\Models\Inventory;
use App\Models\StockTransferInventory;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->stock_transfer);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_name' => ['sometimes', 'required', 'string', 'max:255'],
            'driver_phone_number' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'to_store_id' => ['sometimes', 'required', Rule::exists(Store::class, 'id')],
            'stock_transfer_inventories' => ['exclude_without:stock_transfer_inventories', 'array', 'min:1'],
            'stock_transfer_inventories.*.id' => [
                'sometimes', 'nullable',
                Rule::exists(StockTransferInventory::class, 'id')
                    ->where('stock_transfer_id', $this->stock_transfer->id),
            ],
            'stock_transfer_inventories.*.inventory_id' => ['required_with:stock_transfer_inventories', Rule::exists(Inventory::class, 'id')],
            'stock_transfer_inventories.*.quantity' => ['required_with:stock_transfer_inventories', 'integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:1000'],

        ];
    }
}
