<?php

namespace App\Http\Requests;

use App\Enums\Material;
use App\Enums\PaymentMethod;
use App\Models\DailyGoldPrice;
use App\Models\Inventory;
use App\Models\Sale;
use App\Rules\In;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'discount_code' => ['nullable', 'exists:discounts,code', function ($attribute, $value, $fail) {
                if ($value) {
                    $discount = \App\Models\Discount::where('code', $value)->first();
                    if (! $discount) {
                        return;
                    }

                    if (! $discount->is_active) {
                        $fail('The selected discount code is not active.');
                    } elseif ($discount->expires_at && \Carbon\Carbon::parse($discount->expires_at)->isPast()) {
                        $fail('The selected discount code has expired.');
                    }
                }
            }],
            'customer_user_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone_number' => ['nullable', 'string', 'max:20'],
            'tax' => ['numeric', 'min:0', 'max:100'],
            'payment_method' => ['required', 'string',   new In(PaymentMethod::values(), $caseSensitive = false)],

            'sale_inventories' => 'required|array|min:1',
            'sale_inventories.*.inventory_id' => 'required|exists:inventory,id',
            'sale_inventories.*.quantity' => 'required|integer|min:1',
            'sale_inventories.*.price_per_gram' => 'nullable|numeric|min:0.01',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {

                $sale_inventories = data_get(request()->all(), 'sale_inventories');

                if (is_array($sale_inventories) && ! empty($sale_inventories)) {

                    $inventories = Inventory::whereIn('id', array_column($sale_inventories, 'inventory_id'))
                        ->with('item.category')
                        ->get();

                    $daily_gold_prices = DailyGoldPrice::period('today')
                        ->where(function ($query) use ($inventories) {
                            $query->whereIn('category_id', $inventories->pluck('item.category_id')->toArray())
                                ->orWhereNull('category_id');
                        })
                        ->get();

                    foreach ($sale_inventories as $index => $sale_invent) {
                        $invent = $inventories->where('id', $sale_invent['inventory_id'])
                            ->first();

                        if (! $invent) {
                            continue;
                        }

                        if ($invent->item->material != Material::GOLD->value) {
                            if (! is_numeric($invent->item->price) && ! array_key_exists('price_per_gram', $sale_inventories)) {
                                $error = 'The price per gram is required';
                                $error .= empty($invent->item->material)
                                    ? '.'
                                    : " for the item made of '{$invent->item->material}' material.";

                                $validator->errors()->add("sale_inventories.price_per_gram.$index", $error);
                            }
                        } else {

                            $gold_price = $daily_gold_prices
                                ->when(! empty($invent->item->category_id), function ($query) use ($invent) {
                                    $query->where('category_id', $invent->item->category_id);
                                }, function ($query) {
                                    $query->whereNull('category_id');
                                })
                                ->first();
                            if ($gold_price) {
                                return;
                            }

                            $indexForHuman = $index + 1;
                            $error = empty($invent->item->category->name)
                                ? "Daily gold price for item {$indexForHuman} is required. Please set today\'s gold price in the system."
                                : "Daily gold price for item {$indexForHuman} is required. Please set today's price for '{$invent->item->category->name}' category.";

                            $validator->errors()->add("sale_inventories.price_per_gram.$index", $error);

                        }
                    }
                }
            },
        ];
    }
}
