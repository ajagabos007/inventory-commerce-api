<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Inventory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScrapeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', $this->scrape);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'inventory_id' => ['sometimes', 'required', Rule::exists(Inventory::class, 'id')],
            'quantity' => ['required', 'integer', 'min:1'],
            'customer' => ['nullable', 'array'],
            'customer.id' => ['nullable', Rule::exists(Customer::class, 'id')],
            'customer.name' => ['exclude_with:customer.id', 'sometimes', 'required_without:customer.id', 'string'],
            'customer.email' => ['exclude_with:customer.id', 'string'],
            'customer.phone_number' => ['exclude_with:customer.id', 'string'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
