<?php

namespace App\Traits;

use App\Models\VerificationToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasVerificationTokens
{
    /**
     * Get the user's verification tokes
     */
    public function verificationTokens(): MorphMany
    {
        return $this->morphMany(VerificationToken::class, 'verification_tokenable')->chaperone();
    }

    /**
     * Create a unique verification token
     */
    public function createVerificationToken(?string $verification_type = null): VerificationToken
    {
        // delete expired verification token
        $this->verificationTokens()->where('verification_type', $verification_type)
            ->where('expires_at', '<=', now())
            ->delete();

        $verification_token = $this->verificationTokens()->where('verification_type', $verification_type)
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })->first();

        // update the expiration date
        if (! is_null($verification_token)) {

            $verification_token->expires_at = is_null($verification_token->expires_at) ? null : now()->addDays(30);
            $verification_token->save();

            return $verification_token;
        }

        return $this->verificationTokens()->create([
            'verification_type' => $verification_type,
            'token' => $this->generateDigitToken(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Check is the token exist in database
     */
    private function generateDigitToken(int $digits_len = 6): string
    {
        do {
            // Generate a random 6-digit token
            $token = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::tokenExistsInDatabase($token)); // Ensure token is unique

        return $token;
    }

    /**
     * Check is the token exist in database
     */
    private function tokenExistsInDatabase(string $token): bool
    {
        return \App\Models\VerificationToken::where('token', $token)->exists();
    }
}
