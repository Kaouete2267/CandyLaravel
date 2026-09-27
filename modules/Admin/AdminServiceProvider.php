<?php

namespace Modules\Admin;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxZone;
use Modules\Admin\Filament\AdminPlugin;
use Modules\Admin\Policies\TaxPolicy;

/**
 * Personnalisations transversales du panneau d'administration (apparence, surcharges de vues Filament,
 * contrôle d'accès) qui ne relèvent d'aucun module métier précis.
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
    public static function filamentPlugin(): AdminPlugin
    {
        return AdminPlugin::make();
    }

    public function boot(): void
    {
        $this->app['view']->prependNamespace('filament-panels', __DIR__.'/resources/views/vendor/filament-panels');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->requireLunarPermission(Dashboard::class, 'dashboard');

        foreach ([TaxClassResource::class, TaxZoneResource::class, TaxRateResource::class] as $taxResource) {
            $this->requireLunarPermission($taxResource, 'settings:manage-taxes');
        }

        foreach ([TaxClass::class, TaxZone::class, TaxRate::class] as $taxModel) {
            Gate::policy($taxModel, TaxPolicy::class);
        }
    }

    /**
     * Change la permission exigée par une page ou resource de Lunar. Lunar la lit dans une propriété
     * statique protégée `$permission` (voir Lunar\Admin\Support\Resources\BaseResource et
     * Support\Pages\BaseDashboard), sans API pour la modifier ; et remplacer la classe elle-même n'est pas
     * possible : LunarPanelManager enregistre ses pages et resources en dur dans le panneau.
     *
     * @param  class-string  $class
     */
    protected function requireLunarPermission(string $class, string $permission): void
    {
        Closure::bind(function () use ($permission): void {
            static::$permission = $permission;
        }, null, $class)();
    }
}
