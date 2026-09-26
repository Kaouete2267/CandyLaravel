<?php

namespace Modules\Menu\Filament;

use BackedEnum;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;

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
        'Réglages' => Heroicon::OutlinedAdjustmentsHorizontal,
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
        $panel
            ->sidebarCollapsibleOnDesktop(false)
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => $this->getMobileHeaderStyles());
    }

    /**
     * En mobile, le bouton burger de Filament (seul moyen d'ouvrir le menu, replié hors écran) occupe par
     * défaut sa propre ligne au-dessus de la page. On le fixe en haut à gauche, exactement à l'emplacement de
     * la croix de fermeture du menu ouvert (même largeur que la colonne des groupes : ps-2 + w-20 = 6rem ;
     * même retrait vertical : py-2 de la barre + py-2 de la colonne = 1rem), et on remonte le titre de la page
     * sur la même ligne (padding-top de l'en-tête sticky réduit, voir la surcharge du module Admin), décalé
     * pour laisser la place au bouton. Entre sm et lg, le fil d'Ariane (une ligne + mb-2 = 1.75rem)
     * s'intercale au-dessus du titre et décale d'autant le bouton.
     */
    protected function getMobileHeaderStyles(): string
    {
        return <<<'HTML'
            <style>
                @media (width < 64rem) {
                    .fi-body > .fi-layout-sidebar-toggle-btn-ctn {
                        position: fixed;
                        top: 0;
                        inset-inline-start: 0;
                        z-index: 25;
                        display: flex;
                        justify-content: center;
                        align-items: flex-start;
                        width: 6rem;
                        padding: 1rem 0 0;
                    }

                    .fi-page-header-main-ctn {
                        padding-top: 0;
                    }

                    .fi-header {
                        padding-top: 0.625rem;
                        padding-inline-start: 3.75rem;
                    }
                }

                @media (40rem <= width < 64rem) {
                    .fi-body:has(.fi-header-has-breadcrumbs) > .fi-layout-sidebar-toggle-btn-ctn {
                        padding-top: 2.75rem;
                    }
                }
            </style>
            HTML;
    }

    public function boot(Panel $panel): void {}
}
