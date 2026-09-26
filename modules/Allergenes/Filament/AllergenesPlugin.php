<?php

namespace Modules\Allergenes\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Allergenes\Filament\Resources\AllergenResource;

class AllergenesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'allergenes';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([AllergenResource::class]);
    }

    public function boot(Panel $panel): void {}
}
