<?php

namespace Modules\Menu\Filament;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Support\ModuleStyles;

class MenuPlugin implements Plugin
{
    /**
     * Icône affichée dans la colonne des groupes, par libellé de groupe. Un groupe absent de cette liste
     * reprend l'icône de sa première page.
     *
     * @var array<string, string|BackedEnum|Htmlable>
     */
    protected array $groupIcons = [
        'Catalog' => Heroicon::OutlinedShoppingBag,
        'Catalogue' => Heroicon::OutlinedShoppingBag,
        'Sales' => Heroicon::OutlinedBanknotes,
        'Ventes' => Heroicon::OutlinedBanknotes,
        'Reports' => Heroicon::OutlinedChartBar,
        'Rapports' => Heroicon::OutlinedChartBar,
        'Settings' => Heroicon::OutlinedCog6Tooth,
        'Paramètres' => Heroicon::OutlinedCog6Tooth,
        'Confiserie' => Heroicon::OutlinedCake,
        'Aide' => Heroicon::OutlinedLifebuoy,
    ];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'menu';
    }

    /**
     * @param  array<string, string|BackedEnum|Htmlable>  $groupIcons
     */
    public function groupIcons(array $groupIcons): static
    {
        $this->groupIcons = [...$this->groupIcons, ...$groupIcons];

        return $this;
    }

    public function getGroupIcon(NavigationGroup $group): string|BackedEnum|Htmlable|null
    {
        if ($icon = $group->getIcon()) {
            return $icon;
        }

        if ($icon = $this->groupIcons[$group->getLabel()] ?? null) {
            return $icon;
        }

        /** @var NavigationItem|null $firstItem */
        $firstItem = collect($group->getItems())->first();

        return $firstItem?->getIcon() ?? Heroicon::OutlinedSquares2x2;
    }

    public function register(Panel $panel): void
    {
        // Le repli du menu est géré par le panneau des sous-pages (bouton « ») ; celui de Filament, qui réduit
        // la barre latérale à une colonne d'icônes, ferait doublon.
        $panel->sidebarCollapsibleOnDesktop(false);

        ModuleStyles::register($panel, 'Menu', pages: null);
    }

    public function boot(Panel $panel): void {}
}
