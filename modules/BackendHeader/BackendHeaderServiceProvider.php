<?php

namespace Modules\BackendHeader;

use Illuminate\Support\ServiceProvider;
use Modules\BackendHeader\Filament\BackendHeaderPlugin;

/**
 * Garde l'en-tête des pages du panneau d'administration (titre + actions) visible en défilant, via une
 * surcharge de la vue `filament-panels::components.header`.
 *
 * `loadViewsFrom()` ne fonctionne pas ici : le mécanisme standard de Laravel pour surcharger une vue de
 * package ne regarde QUE `resources/views/vendor/{namespace}` à la racine de l'app (voir
 * Illuminate\Support\ServiceProvider::loadViewsFrom(), qui vérifie `config('view.paths')`), jamais le
 * dossier d'un module. On enregistre donc directement le namespace `filament-panels` de Filament via
 * `prependNamespace()`, qui le fait chercher ICI en premier — avant même le vrai dossier du package —
 * quel que soit l'ordre de démarrage des providers.
 */
class BackendHeaderServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): BackendHeaderPlugin
    {
        return BackendHeaderPlugin::make();
    }

    public function boot(): void
    {
        $this->app['view']->prependNamespace('filament-panels', __DIR__.'/resources/views/vendor/filament-panels');
    }
}
