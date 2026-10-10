<?php

namespace Modules\Fournisseurs\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Fournisseurs\Filament\Resources\SupplierResource;

class FournisseursPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'fournisseurs';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([SupplierResource::class]);
    }

    public function boot(Panel $panel): void {}
}
