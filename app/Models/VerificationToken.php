<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class VerificationToken extends Model
{
    /** @use HasFactory<\Database\Factories\ReferralFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'verification_tokenable_id',
        'verification_tokenable_type',
        'verification_type',
        'token',
        'expires_at',
    ];

    /**
     * Get the parent verificaitonTokenable model (user or post).
     */
    public function verificationTokenable(): MorphTo
    {
        return $this->morphTo();
    }
}
