<?php

namespace Modules\Invitations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Invitations\Models\Invitation;

class AcceptInvitationController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $key = config('bonbon.invitations.session_key');
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation) {
            return response()->view('invitations::invalid', ['expired' => false], 404);
        }

        // Session déjà ouverte avec ce lien : on ne recompte pas l'usage.
        $alreadyIn = $request->session()->get($key) === $invitation->id && $invitation->isActive();

        if (! $alreadyIn) {
            if (! $invitation->canBeOpened()) {
                return response()->view('invitations::invalid', ['expired' => true], 410);
            }

            $invitation->increment('uses_count', 1, ['last_used_at' => now()]);
            $request->session()->regenerate();
            $request->session()->put($key, $invitation->id);
        }

        return redirect()->to(config('bonbon.invitations.landing', '/'));
    }
}
