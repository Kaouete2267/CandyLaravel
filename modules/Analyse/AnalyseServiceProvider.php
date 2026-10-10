<?php

namespace Modules\Analyse;

use Illuminate\Support\ServiceProvider;
use Modules\Analyse\Filament\AnalysePlugin;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\SalesHistory;

/** Analyse des ventes et prévision des commandes, à partir du journal de stock (module Stock). */
class AnalyseServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): AnalysePlugin
    {
        return AnalysePlugin::make();
    }

    public function register(): void
    {
        // Une instance par requête : la page et ses graphiques partagent l'historique chargé et les calculs.
        $this->app->scoped(SalesHistory::class);
        $this->app->scoped(DemandForecast::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
