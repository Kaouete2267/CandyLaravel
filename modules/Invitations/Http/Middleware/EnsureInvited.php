<?php

namespace Modules\Invitations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Invitations\Models\Invitation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve les routes du frontend aux visiteurs venus par un lien d'invitation valide.
 * L'invitation est revérifiée à chaque requête : révoquée ou expirée, l'accès s'arrête immédiatement.
 */
class EnsureInvited
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('bonbon.invitations.session_key');
        $invitation = ($id = $request->session()->get($key)) ? Invitation::find($id) : null;

        if (! $invitation || ! $invitation->isActive()) {
            $request->session()->forget($key);

            return response()->view('invitations::invalid', ['expired' => (bool) $invitation], 403);
        }

        return $next($request);
    }
}
