<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\Auth\VerifyEmail;
use Illuminate\Http\Request;

use function Illuminate\Support\defer;

class VerificationController extends Controller
{
    /**
     * @method POST api/email/verification-token
     */
    public function sendEmailVerificationToken()
    {
        $user = auth()->user();

        if (is_null($user)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthenticated',
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Your email has already been verified',
            ], 422);
        }

        $verification_token = $user->createVerificationToken('email');

        defer(function () use ($user, $verification_token) {
            $user->notify(new VerifyEmail($verification_token));
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Email verification token sent to your email successfully.',

        ]);
    }

    /**
     * @method POST api/email/verify
     */
    public function verifyEmail(Request $request)
    {
        $validated = $request->validate($rules = [
            'otp_code' => 'required|exists:verification_tokens,token',
        ], $messages = ['otp_code.exists' => 'Invalid or expired token..!']);

        $user = auth()->user();

        if (is_null($user)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Unauthenticated',
            ], 401);
        }

        $verification_token = $user->verificationTokens()
            ->where('token', $validated['otp_code'])
            ->first();

        if (is_null($verification_token)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid token',
            ], 401);
        }

        $user->markEmailAsVerified();

        $user->verificationTokens()
            ->where('verification_type', $verification_token->verification_type)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully.',

        ]);
    }
}
