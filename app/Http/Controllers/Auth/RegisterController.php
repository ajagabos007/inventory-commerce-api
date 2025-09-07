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
     * @return \App\Http\Resources\UserResource
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);

        defer(function () use ($user, $validated) {
            if (array_key_exists('referral_code', $validated)) {
                $refferal_code = \App\Models\ReferralCode::where('code', $validated['referral_code'])->first();
                $user->referrer_user_id = $refferal_code->user_id ?? null;
            }

            $user->password = Hash::make($validated['password']);
            $user->save();

            event(new Registered($user));

        });

        $token = $user->createToken($request->userAgent())->plainTextToken;

        $user_resource = (new UserResource($user))->additional([
            'token' => $token,
            'message' => 'User registered successfully',
        ]);

        return $user_resource;
    }
}
