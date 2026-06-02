<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\AuditActionType;
use App\Enums\AuditModule;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Create the controller with its service dependencies.
     *
     * The framework resolves this constructor before action-specific route
     * middleware, permissions, validation, and authorization are applied.
     *
     * @param  AuditLogService  $auditLogService
     */
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * Authenticate a user and issue an API access token.
     *
     * Public authentication route, with throttling applied by route middleware.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON payload with the API token, token type, and expiration time.
     *
     * @return JsonResponse
     *
     * @param  Request  $request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $loginIdentifierFingerprint = hash('sha256', mb_strtolower($credentials['email']));

        if (Auth::attempt($credentials)) {
            /** @var User $user */
            $user = Auth::user();

            if ($user->status !== User::STATUS_ACTIVE) {
                Auth::logout();
                $this->logFailedLogin($request, $user, 'inactive_user', $loginIdentifierFingerprint);

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

        $attemptedUser = User::query()
            ->where('email', $credentials['email'])
            ->first(['id']);
        $this->logFailedLogin($request, $attemptedUser, 'invalid_credentials', $loginIdentifierFingerprint);

        return response()->json([
            'message' => 'Invalid credentials.',
        ], 401);
    }

    /**
     * Revoke the authenticated user's current API access token.
     *
     * Authenticated users only; route middleware requires a valid Sanctum token.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @return JsonResponse
     *
     * @param  Request  $request
     */
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

    /**
     * Handle the log failed login action for login records.
     *
     * Public authentication route, with throttling applied by route middleware.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $user, $failureReason, $loginIdentifierFingerprint.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  Request  $request
     * @param  ?User  $user
     * @param  string  $failureReason
     * @param  string  $loginIdentifierFingerprint
     * @return void
     */
    private function logFailedLogin(Request $request, ?User $user, string $failureReason, string $loginIdentifierFingerprint): void
    {
        $this->auditLogService->record(
            actorUserId: null,
            actionType: AuditActionType::AUTH_LOGIN_FAILED,
            module: AuditModule::AUTH,
            targetEntityType: 'user',
            targetEntityId: $user?->id,
            metadata: [
                'failure_reason' => $failureReason,
                'account_found' => $user !== null,
                'login_identifier_fingerprint' => $loginIdentifierFingerprint,
            ],
            requestContext: [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );
    }
}
