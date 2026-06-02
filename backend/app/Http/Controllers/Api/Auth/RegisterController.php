<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    /**
     * Register a new user account.
     *
     * Public authentication route, with throttling applied by route middleware.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON payload with the created user account.
     *
     * @return JsonResponse
     *
     * @param  Request  $request
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['prohibited'],
            'roles' => ['prohibited'],
            'permissions' => ['prohibited'],
            'status' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'invited_at' => ['prohibited'],
            'activated_at' => ['prohibited'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'status' => 'active', // Or 'invited' and send an invitation
            'activated_at' => now(),
        ]);

        $studentRole = Role::findOrCreate('student', 'web');
        $user->assignRole($studentRole);

        return response()->json([
            'message' => 'User registered successfully',
        ]);
    }
}
