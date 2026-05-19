<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InvitationController extends Controller
{
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
