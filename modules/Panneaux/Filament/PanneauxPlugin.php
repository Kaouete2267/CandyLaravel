<?php

namespace Modules\Panneaux\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Modules\Panneaux\Filament\Resources\PanelResource;
use Modules\Panneaux\Http\Controllers\PrintPanelController;

class PanneauxPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'panneaux';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([PanelResource::class])
            // /admin/panneaux/{panel}/impression : réservé au staff connecté (nom : filament.lunar.panneaux.print)
            ->authenticatedRoutes(function () {
                Route::get('panneaux/{panel}/impression', PrintPanelController::class)->name('panneaux.print');
            });
    }

    public function boot(Panel $panel): void {}
}
