<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

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

            $this->auditLogService->record(
                actorUserId: $user->id,
                actionType: AuditActionType::AUTH_LOGIN,
                module: AuditModule::AUTH,
                targetEntityType: 'user',
                targetEntityId: $user->id,
                metadata: [
                    'status' => $user->status,
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            );

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

        if ($user !== null) {
            $this->auditLogService->record(
                actorUserId: $user->id,
                actionType: AuditActionType::AUTH_LOGOUT,
                module: AuditModule::AUTH,
                targetEntityType: 'user',
                targetEntityId: $user->id,
                metadata: [
                    'revoked_current_access' => $token !== null,
                ],
            );
        }

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
