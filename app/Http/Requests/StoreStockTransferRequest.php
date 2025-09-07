<?php

namespace App\Http\Requests;

use App\Models\Inventory;
use App\Models\StockTransfer;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', StockTransfer::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_name' => ['required', 'string', 'max:255'],
            'driver_phone_number' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/'],
            'to_store_id' => ['required', Rule::exists(Store::class, 'id')],
            'stock_transfer_inventories' => ['array', 'min:1'],
            'stock_transfer_inventories.*.inventory_id' => ['required', Rule::exists(Inventory::class, 'id')],
            'stock_transfer_inventories.*.quantity' => ['integer', 'min:1'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
