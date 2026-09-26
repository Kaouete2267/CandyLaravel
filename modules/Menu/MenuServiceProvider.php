<?php

namespace Modules\Menu;

use Illuminate\Support\ServiceProvider;
use Modules\Menu\Filament\MenuPlugin;

/**
 * Remplace la barre latérale du panneau d'administration par un menu à deux colonnes : une colonne étroite
 * listant les groupes de navigation (icône + titre), et un panneau affichant les pages du groupe cliqué.
 *
 * La vue `filament-panels::livewire.sidebar` est surchargée via `prependNamespace()` (même mécanisme et même
 * raison que Modules\Admin\AdminServiceProvider).
 */
class MenuServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): MenuPlugin
    {
        return MenuPlugin::make();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'menu');
        $this->app['view']->prependNamespace('filament-panels', __DIR__.'/resources/views/vendor/filament-panels');
    }
}
