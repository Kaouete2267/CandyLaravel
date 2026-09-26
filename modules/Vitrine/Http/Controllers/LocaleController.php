<?php

namespace Modules\Vitrine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        abort_unless(array_key_exists($locale, config('bonbon.locales')), 404);

        $request->session()->put('locale', $locale);

        return redirect()->back(fallback: route('vitrine.catalogue'));
    }
}
