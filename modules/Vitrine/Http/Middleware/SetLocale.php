<?php

namespace Modules\Vitrine\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Langue du visiteur : choix mémorisé en session, sinon langue du navigateur, sinon langue par défaut. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('bonbon.locales'));

        $locale = $request->session()->get('locale');

        if (! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? $supported[0];
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
