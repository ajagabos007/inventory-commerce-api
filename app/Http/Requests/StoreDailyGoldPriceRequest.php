<?php

namespace App\Http\Requests;

use App\Models\DailyGoldPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyGoldPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', DailyGoldPrice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable', 'exists:categories,id',
                Rule::unique('daily_gold_prices', 'category_id')
                    ->where('category_id', $this->input('category_id'))
                    ->where('recorded_on', $this->input('recorded_on')),
            ],
            'price_per_gram' => ['required', 'numeric', 'max:99999999'],
            'recorded_on' => [
                'nullable', 'date',
                Rule::unique('daily_gold_prices', 'recorded_on')
                    ->where('category_id', $this->input('category_id'))
                    ->where('recorded_on', $this->input('recorded_on')),
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'category',
        ];
    }
}
