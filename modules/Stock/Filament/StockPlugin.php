<?php

namespace Modules\Stock\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Stock\Filament\Pages\StockGrid;
use Modules\Stock\Filament\Pages\StockSettings;
use Modules\Stock\Filament\Resources\StockMovementResource;
use Modules\Support\ModuleStyles;

class StockPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'stock';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([StockGrid::class, StockSettings::class])
            ->resources([StockMovementResource::class]);

        ModuleStyles::register($panel, 'Stock', pages: [StockGrid::class]);
    }

    public function boot(Panel $panel): void {}
}
