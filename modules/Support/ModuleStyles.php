<?php

namespace Modules\Support;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;

/**
 * Charge un point d'entrée CSS d'un module (modules/<Nom>/resources/css/<entrée>.css, compilé à part par Vite,
 * voir vite.config.js) dans le <head> du panneau, uniquement sur les pages indiquées : un <link> vers le fichier
 * compilé, pas de CSS en ligne. Un module peut avoir plusieurs entrées (ex. une globale et une pour certaines pages).
 */
class ModuleStyles
{
    /**
     * @param  array<int, class-string>|null  $pages  Pages (classes Livewire) où charger le CSS ; null = toutes.
     */
    public static function register(Panel $panel, string $module, ?array $pages, string $entry = 'module'): void
    {
        $panel->renderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => app(Vite::class)(static::entryPoint($module, $entry)),
            scopes: $pages,
        );
    }

    public static function entryPoint(string $module, string $entry = 'module'): string
    {
        return "modules/{$module}/resources/css/{$entry}.css";
    }
}
