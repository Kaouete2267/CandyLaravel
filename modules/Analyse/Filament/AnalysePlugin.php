<?php

namespace Modules\Analyse\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Widgets\Widget;
use Modules\Analyse\Filament\Pages\SalesAnalysis;
use Modules\Analyse\Filament\Pages\SalesProjection;
use Modules\Analyse\Filament\Pages\UpcomingOrders;
use Modules\Analyse\Filament\Widgets\BrandRankingChart;
use Modules\Analyse\Filament\Widgets\BrandShareChart;
use Modules\Analyse\Filament\Widgets\BrandTrendChart;
use Modules\Analyse\Filament\Widgets\MonthlySalesChart;
use Modules\Analyse\Filament\Widgets\OrdersOverview;
use Modules\Analyse\Filament\Widgets\ProjectedSalesChart;
use Modules\Analyse\Filament\Widgets\ProjectedStockChart;
use Modules\Analyse\Filament\Widgets\ProjectionOverview;
use Modules\Analyse\Filament\Widgets\SalesOverview;

class AnalysePlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'analyse';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([SalesAnalysis::class, SalesProjection::class, UpcomingOrders::class])
            // Déclarés pour que Livewire les retrouve lors de leurs requêtes (chargement différé, filtres) ;
            // le tableau de bord Lunar a sa propre liste de widgets et ne les affiche pas.
            ->widgets(self::widgets());
    }

    /** @return list<class-string<Widget>> */
    public static function widgets(): array
    {
        return [
            SalesOverview::class, MonthlySalesChart::class, BrandTrendChart::class, BrandShareChart::class, BrandRankingChart::class,
            ProjectionOverview::class, ProjectedSalesChart::class, ProjectedStockChart::class,
            OrdersOverview::class,
        ];
    }

    public function boot(Panel $panel): void {}
}
