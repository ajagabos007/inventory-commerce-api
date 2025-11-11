<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

use function Illuminate\Support\defer;

class RegisterController extends Controller
{
    /**
     * Register a user
     *
     * @method POST api/register
     *
     * @return UserResource
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);

        defer(function () use ($user, $validated) {
            event(new Registered($user));
        });

        $token = $user->createToken($request->userAgent())->plainTextToken;

        return (new UserResource($user))->additional([
            'token' => $token,
            'message' => 'User registered successfully',
        ]);
    }
}
