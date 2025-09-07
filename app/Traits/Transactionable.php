<?php

namespace App\Traits;

use App\Models\TransactionHistory;
use App\Models\TransactionRequest;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Transactionable
{
    /**
     * Get all of the resource transaction requests.
     */
    public function transactionRequests(): MorphMany
    {
        return $this->morphMany(TransactionRequest::class, 'transactionable');
    }

    /**
     * Get all of the resource transaction histories.
     */
    public function transactionHistories(): MorphMany
    {
        return $this->morphMany(TransactionHistory::class, 'transactionable');
    }
}
