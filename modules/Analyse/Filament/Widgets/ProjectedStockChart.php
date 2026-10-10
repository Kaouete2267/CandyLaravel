<?php

namespace Modules\Analyse\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Filament\Pages\SalesProjection;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\ForecastSettings;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\ChartColors;

/** Stock total projeté semaine par semaine, sans nouvelle livraison et avec les commandes suggérées. */
class ProjectedStockChart extends ChartWidget
{
    use AnalyticsWidget;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Stock projeté';

    protected ?string $description = 'Stock total en kg : avec les livraisons suggérées (page Commandes) et, en pointillés, sans nouvelle livraison.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $today = CarbonImmutable::today();
        $curve = app(DemandForecast::class)->stockCurve(
            ForecastSettings::fromFilters($this->pageFilters),
            $today,
            SalesProjection::horizonEnd($this->pageFilters, $today),
            app(SalesHistory::class)->variantIds($this->brandFilter()),
        );

        // Un point par semaine (et le dernier jour) pour garder le graphique lisible.
        $dates = array_keys($curve);
        $points = array_filter($curve, fn (string $date) => array_search($date, $dates, true) % 7 === 0 || $date === array_key_last($curve), ARRAY_FILTER_USE_KEY);

        return [
            'labels' => array_map(fn (string $date) => CarbonImmutable::parse($date)->format('d/m'), array_keys($points)),
            'datasets' => [
                [
                    'label' => 'Avec les commandes suggérées',
                    'data' => array_map(fn (array $point) => round($point['with']), array_values($points)),
                    'borderColor' => ChartColors::PRIMARY,
                    'backgroundColor' => ChartColors::alpha(ChartColors::PRIMARY, 0.1),
                    'fill' => true,
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'stepped' => false,
                ],
                [
                    'label' => 'Sans nouvelle livraison',
                    'data' => array_map(fn (array $point) => round($point['without']), array_values($points)),
                    'borderColor' => ChartColors::MUTED,
                    'backgroundColor' => ChartColors::MUTED,
                    'borderDash' => [5, 4],
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(127,127,127,.15)']], 'x' => ['grid' => ['display' => false]]],
        ];
    }
}
