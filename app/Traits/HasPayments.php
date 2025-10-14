<?php

namespace App\Traits;

use App\Models\Payment;
use App\Models\Payable;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;


trait HasPayments
{
    /**
     * Define a polymorphic one-to-many relationship for payables.
     *
     * @return MorphMany<Payable>
     */
    public function payables(): MorphMany
    {
        return $this->morphMany(Payable::class, 'payable');
    }

    /**
     * Define a polymorphic many-to-many relationship for payments.
     *
     * @return MorphToMany<Payment>
     */
    public function payments(): MorphToMany
    {
        return $this->morphToMany(Payment::class, 'payable')
            ->withPivot('amount','verifier_id', 'verified_at')
            ->withTimestamps();
    }

    /**
     * Boot the trait and handle the payable delete event.
     * Deletes associated comments when the model is deleted.
     */
    public static function bootHasPayments(): void
    {
        static::deleted(function ($model) {
            $model->payments()->delete();
        });
    }

}
