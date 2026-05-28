<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (Auth::attempt($credentials)) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->status !== User::STATUS_ACTIVE) {
                Auth::logout();

                return response()->json([
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            $expiresAt = now()->addMinutes((int) config('sanctum.expiration', 120));
            $token = $user->createToken(
                name: 'auth_token',
                abilities: ['*'],
                expiresAt: $expiresAt
            )->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        }

        return response()->json([
            'message' => 'Invalid credentials.',
        ], 401);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token !== null) {
            $token->delete();
        } else {
            $user?->tokens()->delete();
        }

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
