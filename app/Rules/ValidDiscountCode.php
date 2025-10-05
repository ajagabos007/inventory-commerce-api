<?php

namespace App\Rules;

use App\Models\Discount;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDiscountCode implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (blank($value)) {
            return;
        }

        $discount = Discount::where('code', $value)->first();

        if (! $discount) {
            return; // exists rule will handle this
        }

        if (! $discount->is_active) {
            $fail('The selected discount code is not active.');
        } elseif ($discount->expires_at && Carbon::parse($discount->expires_at)->isPast()) {
            $fail('The selected discount code has expired.');
        }
    }
}
