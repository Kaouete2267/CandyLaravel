<?php

namespace Modules\Admin\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Modules\Admin\Http\Middleware\RedirectFromForbiddenDashboard;

class AdminPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'admin';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->authMiddleware([RedirectFromForbiddenDashboard::class])
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => view('admin::camera-capture')->render());
    }

    public function boot(Panel $panel): void {}
}
