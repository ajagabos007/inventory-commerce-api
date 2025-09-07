<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\Auth\PasswordUpdated;
use App\Notifications\Auth\ResetPasswordToken;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use function Illuminate\Support\defer;

class ResetPasswordController extends Controller
{
    /**
     * Send pass reset token to user
     *
     * @method POST  api/forget-password
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function sendPasswordResetToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        if (! is_null($user = User::where('email', $validated['email'])->first())) {
            do {

                $token = mt_rand(100000, 999999);
                $checked_token = DB::table('password_reset_tokens')->where('email', '<>', $user->email)->where('token', $token)->first();
                $token_is_taken = $checked_token != null;

                // clear expired token again if token is taken
                if ($token_is_taken) {
                    Password::broker()->getRepository()->deleteExpired();
                }

            } while ($token_is_taken);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'email' => $request->email,
                    'token' => $token, // Hash the token
                    'created_at' => now(),
                ]
            );

            defer(function () use ($user, $token) {
                $user->notify(new ResetPasswordToken($token));
            });
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset token has been sent to the provided email successfully.',
        ]);
    }

    /**
     * Verify password reset token
     *
     * @method PUT|PATCH  api/verify-password-reset-token
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function verifyPasswordResetToken(Request $request): JsonResponse
    {
        Password::broker()->getRepository()->deleteExpired();

        $validated = $request->validate(
            $rules = ['token' => 'required|exists:password_reset_tokens,token'],
            $messages = ['token.exists' => 'Invalid or expired token..!']
        );

        $password_reset_tokens = DB::table('password_reset_tokens')
            ->where('token', $validated['token'])
            ->first();

        $user = User::where('email', $password_reset_tokens->email)->first();

        if (is_null($user)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid or expired token..!',

            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'email' => $user->email,
            ],
            'message' => 'Token is valid',
        ]);
    }

    /**
     * Reset password via password reset token
     *
     * @method PUT|PATCH  api/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validate(
            $rules = [
                'token' => 'required|exists:password_reset_tokens,token',
                'password' => 'required|min:8|confirmed',
            ],
            $mesages = [
                'token.exists' => 'Invalid or expired token..!',
            ]
        );

        $password_reset = DB::table('password_reset_tokens')
            ->where('token', $validated['token'])
            ->first();

        $user = User::where('email', $password_reset->email)->first();

        if (is_null($user)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'User not found for the password reset..!',

            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        defer(function () use ($user, $password_reset) {

            DB::table('password_reset_tokens')
                ->where('email', $password_reset->email)
                ->where('token', $password_reset->token)
                ->delete();

            event(new PasswordReset($user));

            $user->notify(new PasswordUpdated($user));

        });

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successfully.',

        ]);
    }

    /**
     * Change password for auth user providing the old password
     *
     * @method PUT|PATCH  api/change-password
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        // Check if the provided password is correct
        if (is_null($user) || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Invalid password!',

            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        defer(function () use ($user) {

            event(new PasswordReset($user));

            $user->notify(new PasswordUpdated($user));

        });

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successfully.',

        ], 200);
    }
}
