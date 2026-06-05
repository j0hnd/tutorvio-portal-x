<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    /**
     * Send an invitation to create or activate a user account.
     *
     * Authenticated users only; invitation creation also requires admin or staff role plus users.create permission.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action.
     * Inline validation rejects missing or invalid request data before processing.
     * Returns a JSON response containing the requested data.
     *
     * @return JsonResponse
     *
     * @param  Request  $request
     */
    public function invite(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $token = Str::random(32);

        UserInvitation::create([
            'email' => $request->email,
            'token' => $token,
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // Send invitation email with token...

        return response()->json(['message' => 'Invitation sent.']);
    }

    /**
     * Accept an account invitation token.
     *
     * Public authentication route, with throttling applied by route middleware.
     * Important request values come from query parameters, JSON body fields, or the typed FormRequest used by this action. Route model parameters include $token.
     * Request data is constrained by route model binding, middleware, and any validation performed by the called services.
     * Returns a JSON response containing the requested data.
     *
     * @param  mixed  $token
     * @return JsonResponse
     *
     * @param  Request  $request
     */
    public function accept(Request $request, $token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();

        if ($invitation->expires_at < Carbon::now()) {
            return response()->json(['message' => 'Invitation has expired.'], 400);
        }

        // Logic to register the user from the invitation
        // ...

        $invitation->delete();

        return response()->json(['message' => 'Invitation accepted.']);
    }
}
