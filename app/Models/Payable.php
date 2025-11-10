<?php

namespace App\Models;

use App\Traits\ModelRequestLoader;
use Database\Factories\PayableFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payable extends Model
{
    /** @use HasFactory<PayableFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_id',
        'payable_id',
        'payable_type',
        'amount',
        'metadata',
        'verifier_id',
        'verified_at',
    ];

    /**
     * Get the parent of the payable model (studentFee etc.).
     *
     * @return MorphTo<Model>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the payment of the payable.
     *
     * @return BelongsTo<Payment>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    /**
     * Get the verifier of the payable.
     *
     * @return BelongsTo<User>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }
}
