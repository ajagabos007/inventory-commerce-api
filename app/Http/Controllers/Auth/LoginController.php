<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use function Illuminate\Support\defer;

class LoginController extends Controller
{
    /**
     * Login a user
     *
     * @param  App\Http\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user == null || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'These credentials do not match our records.',
                'errors' => [
                    'email' => ['These credentials do not match our records.'],
                ],
            ], 422);
        }

        if (! is_null($user->deactivated_at)) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Your account was deactivated, please contact admin',
                'errors' => [
                    'email' => ['Your account was deactivated, please contact admin'],
                ],
            ], 401);
        }

        $token = $user->createToken($request->userAgent())->plainTextToken;
        $remember_me = $validation['remember_me'] ?? false;

        defer(function () use ($user, $remember_me) {
            event(new Login($guard = 'api', $user, $remember_me));
        });

        $user->all_permissions = $user->getAllPermissions();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successfully',
            'user' => $user->load([
                'staff',
            ])->append([
                'is_admin', 'is_staff',
            ]),
            'token' => $token,
        ]);
    }

    /**
     * Logout auth user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request...
        $request->user()->currentAccessToken()->delete();

        event(new Logout($guard = 'api', $request->user));

        return response()->json([
            'status' => 'success',
            'message' => 'Logout successfully',
        ]);
    }
}
