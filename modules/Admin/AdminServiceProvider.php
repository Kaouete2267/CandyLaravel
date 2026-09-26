<?php

namespace Modules\Admin;

use Illuminate\Support\ServiceProvider;

/**
 * Personnalisations transversales du panneau d'administration (apparence, surcharges de vues Filament) qui
 * ne relèvent d'aucun module métier précis.
 *
 * `loadViewsFrom()` ne fonctionne pas ici : le mécanisme standard de Laravel pour surcharger une vue de
 * package ne regarde QUE `resources/views/vendor/{namespace}` à la racine de l'app (voir
 * Illuminate\Support\ServiceProvider::loadViewsFrom(), qui vérifie `config('view.paths')`), jamais le
 * dossier d'un module. On enregistre donc directement le namespace `filament-panels` de Filament via
 * `prependNamespace()`, qui le fait chercher ICI en premier — avant même le vrai dossier du package —
 * quel que soit l'ordre de démarrage des providers.
 */
class AdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['view']->prependNamespace('filament-panels', __DIR__.'/resources/views/vendor/filament-panels');
    }
}
