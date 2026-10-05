<?php

namespace Modules\Ia\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Modules\Ia\Filament\Pages\CreateProductWithAi;
use Modules\Ia\Filament\Pages\IaSettings;
use Modules\Support\ModuleStyles;

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

        ModuleStyles::register($panel, 'Ia', pages: [EditProduct::class]);
    }

    public function boot(Panel $panel): void {}
}
