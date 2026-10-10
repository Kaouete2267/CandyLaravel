<?php

namespace Modules\Analyse\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Filament\Pages\SalesProjection;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\ForecastSettings;
use Modules\Analyse\Services\SalesHistory;

/** Chiffres clés de la projection : volume attendu, tendance appliquée, ruptures annoncées, prochaine livraison. */
class ProjectionOverview extends StatsOverviewWidget
{
    use AnalyticsWidget;

    protected function getColumns(): int
    {
        return 4;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $forecast = app(DemandForecast::class);
        $settings = ForecastSettings::fromFilters($this->pageFilters);
        $today = CarbonImmutable::today();
        $end = SalesProjection::horizonEnd($this->pageFilters, $today);
        $ids = array_flip(app(SalesHistory::class)->variantIds($this->brandFilter()));

        $rows = array_intersect_key($forecast->projection($settings, $today, $end), $ids);
        $projected = array_sum(array_column($rows, 'projected'));
        $lastYear = array_sum(array_column($rows, 'last_year'));
        $ruptures = count(array_filter($rows, fn (array $row) => $row['status'] === 'rupture'));
        $tensions = count(array_filter($rows, fn (array $row) => $row['status'] === 'tension'));
        $trend = $forecast->trend($settings, $today);
        $next = $forecast->schedule()->upcoming($today, 1)[0] ?? null;

        $change = $lastYear > 0 ? ($projected - $lastYear) / $lastYear * 100 : null;

        return [
            Stat::make('Ventes projetées', number_format($projected, 0, ',', ' ').' kg')
                ->description('jusqu\'au '.$end->format('d/m/Y').($change === null ? '' : sprintf(' — %+.1f %% vs N-1 (%s)', $change, number_format($lastYear, 0, ',', ' '))))
                ->descriptionColor('gray'),
            Stat::make('Tendance appliquée', sprintf('%+.1f %%', ($trend - 1) * 100))
                ->description(ForecastSettings::METHODS[$settings->method])
                ->descriptionColor('gray')
                ->descriptionIcon($trend >= 1 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown),
            Stat::make('Ruptures avant livraison', $ruptures)
                ->description($tensions > 0 ? "+ {$tensions} stock(s) tendu(s)" : 'Aucun stock tendu')
                ->descriptionColor($tensions > 0 ? 'warning' : 'gray')
                ->color($ruptures > 0 ? 'danger' : 'success')
                ->icon($ruptures > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle),
            Stat::make('Prochaine livraison', $next ? $next['date']->format('d/m/Y') : '—')
                ->description($next ? $next['label'] : 'Aucune livraison planifiée')
                ->descriptionColor('gray')
                ->icon(Heroicon::Truck),
        ];
    }
}
