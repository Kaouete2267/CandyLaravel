<?php

namespace Modules\Invitations\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Modules\Invitations\Filament\Resources\InvitationResource;

class InvitationsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'invitations';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([InvitationResource::class]);
    }

    public function boot(Panel $panel): void {}
}
