<?php

namespace Modules\Types\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Types\Filament\Resources\CandyTypeResource;

class TypesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'types';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([CandyTypeResource::class]);
    }

    public function boot(Panel $panel): void {}
}
