<?php

namespace Modules\Admin;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\TaxClassResource;
use Lunar\Admin\Filament\Resources\TaxRateResource;
use Lunar\Admin\Filament\Resources\TaxZoneResource;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxZone;
use Modules\Admin\Filament\AdminPlugin;
use Modules\Admin\Filament\Extensions\ProductTableSortingExtension;
use Modules\Admin\Policies\TaxPolicy;

/**
 * Personnalisations transversales du panneau d'administration (apparence, contrôle d'accès) qui ne
 * relèvent d'aucun module métier précis.
 */
class AdminServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): AdminPlugin
    {
        return AdminPlugin::make();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'admin');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->requireLunarPermission(Dashboard::class, 'dashboard');

        foreach ([TaxClassResource::class, TaxZoneResource::class, TaxRateResource::class] as $taxResource) {
            $this->requireLunarPermission($taxResource, 'settings:manage-taxes');
        }

        foreach ([TaxClass::class, TaxZone::class, TaxRate::class] as $taxModel) {
            Gate::policy($taxModel, TaxPolicy::class);
        }

        LunarPanel::extensions([
            ProductResource::class => ProductTableSortingExtension::class,
        ]);
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
