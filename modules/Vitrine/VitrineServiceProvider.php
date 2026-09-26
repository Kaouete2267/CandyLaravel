<?php

namespace Modules\Vitrine;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Vitrine\Http\Middleware\SetLocale;

class VitrineServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'vitrine');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'vitrine');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        $router->aliasMiddleware('locale', SetLocale::class);
    }
}
