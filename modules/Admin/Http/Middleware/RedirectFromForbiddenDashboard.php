<?php

namespace Modules\Admin\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Lunar\Admin\Filament\Pages\Dashboard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le tableau de bord est la page d'accueil du panneau : c'est là que mènent la connexion et le logo. Le
 * personnel sans la permission `dashboard` y est redirigé vers la première page de son menu plutôt que de
 * tomber sur une erreur 403 (qu'il garde s'il n'a accès à aucune page).
 */
class RedirectFromForbiddenDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(Dashboard::getRouteName()) || Filament::auth()->user()?->can('dashboard')) {
            return $next($request);
        }

        $url = Filament::getCurrentPanel()->getRedirectUrl();

        return $url === $request->url() ? $next($request) : redirect($url);
    }
}
