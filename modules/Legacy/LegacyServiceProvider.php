<?php

namespace Modules\Legacy;

use Illuminate\Support\ServiceProvider;
use Modules\Legacy\Console\ImportLegacyCommand;

/** Import du jeu de données de l'ancienne application : `php artisan bonbon:import-legacy`. */
class LegacyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ImportLegacyCommand::class]);
        }
    }
}
