<?php

namespace Modules\BackendHeader\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Support\ModuleStyles;

class BackendHeaderPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'backend-header';
    }

    public function register(Panel $panel): void
    {
        ModuleStyles::register($panel, 'BackendHeader', pages: null);
    }

    public function boot(Panel $panel): void {}
}
