<?php

namespace App\Http\Requests;

use App\Enums\Type;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Scrape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScrapeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Scrape::class);
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
            'type' => [
                'required',
                Rule::in(Type::values()),
            ],
            'quantity' => ['required', 'integer', 'min:1'],
            'customer' => [
                'exclude_unless:type,'.Type::RETURNED->value,
                'required_if:type,'.Type::RETURNED->value,
                'array',
            ],

            'customer.id' => [
                'exclude_unless:type,'.Type::RETURNED->value,
                'required_without:customer.name',
                Rule::exists(Customer::class, 'id'),
            ],
            'customer.name' => [
                'required_if:type,'.Type::RETURNED->value,
                'exclude_if:customer.id,*',
                'string',
            ],
            'customer.email' => [
                'nullable',
                'exclude_if:customer.id,*',
                'string',
            ],
            'customer.phone_number' => [
                'nullable',
                'exclude_if:customer.id,*',
                'string',
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the attributes that apply to the request.
     */
    public function attributes(): array
    {
        return [
            'inventory_id' => 'Inventory',
            'type' => 'Type',
            'quantity' => 'Quantity',
            'customer.id' => 'Customer',
            'customer.name' => 'Customer Name',
            'customer.email' => 'Customer Email',
            'customer.phone_number' => 'Customer Phone Number',
        ];
    }
}
