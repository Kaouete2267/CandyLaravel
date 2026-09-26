<?php

namespace Modules\Invitations;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Invitations\Filament\InvitationsPlugin;
use Modules\Invitations\Http\Middleware\EnsureInvited;

class InvitationsServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): InvitationsPlugin
    {
        return InvitationsPlugin::make();
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'invitations');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');

        // Middleware « invited » : à poser sur toute route du frontend réservée aux invités.
        $router->aliasMiddleware('invited', EnsureInvited::class);
    }
}
