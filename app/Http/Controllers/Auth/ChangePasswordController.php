<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Notifications\PasswordUpdated;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

use function Illuminate\Support\defer;

class ChangePasswordController extends Controller
{
    /**
     * Change user password
     *
     * @method PUT|PATCH api/change-password
     */
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = auth()->user();

        // Check if the provided password is correct
        if (is_null($user) || ! Hash::check($validated['password'], $user->password)) {

            return response()->json([
                'status' => 'failed',
                'message' => 'Incorrect password',
                'errors' => [
                    'password' => ['Incorrect password'],
                ],
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
            'message' => 'Password updated successfully',
        ]);

    }
}
