<?php

namespace App\Http\Requests;

use App\Models\Discount;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->discount);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique(Discount::class, 'code')->ignore($this->discount),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'percentage' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'expires_at' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $newDate = Carbon::parse($value);
                    $today = now()->startOfDay();

                    if ($newDate->gt($today)) {
                        return;
                    }

                    $current_expiry = $today;

                    if ($this->discount && $this->discount->expires_at) {
                        $current_expiry = Carbon::parse($this->discount->expires_at)->startOfDay();
                    }

                    if ($newDate->lt($current_expiry) && $today->gte($current_expiry)) {
                        $fail("The {$attribute} must be a date after or equal to the current expiration date {$current_expiry->toDateString()}.");
                    } else {
                        $fail("The {$attribute} must be a date after today.");
                    }

                },
            ],
            'is_active' => ['boolean'],
        ];
    }
}
