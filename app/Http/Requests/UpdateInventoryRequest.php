<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInventoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->inventory);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'integer', 'min:0'],
            'serial_number' => ['nullable', 'max:255', 'unique:inventories,serial_number,'.$this->inventory->id],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:in_stock,out_of_stock,low_stock'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $inventory = $this->inventory;

                if ($inventory && $inventory->productVariant->is_serialized) {
                    if ($this->has('quantity') && $this->input('quantity') != $inventory->quantity) {
                        $validator->errors()->add('quantity', 'The quantity cannot be changed for serialized products.');
                    }

                    if ($this->has('serial_number') && ! $this->input('serial_number')) {
                        $validator->errors()->add('serial_number', 'The serial number is required for serialized products.');
                    }
                }

            },
        ];
    }
}
