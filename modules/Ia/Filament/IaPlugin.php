<?php

namespace Modules\Ia\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Ia\Filament\Pages\CreateProductWithAi;
use Modules\Ia\Filament\Pages\IaSettings;
use Modules\Ia\Filament\Resources\IngredientTermResource;

class IaPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'ia';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([CreateProductWithAi::class, IaSettings::class]);
        $panel->resources([IngredientTermResource::class]);
    }

    public function boot(Panel $panel): void {}
}
