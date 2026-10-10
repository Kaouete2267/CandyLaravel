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

/** Ventes projetées mois par mois, à côté des ventes réelles des mêmes mois l'an dernier. */
class ProjectedSalesChart extends ChartWidget
{
    use AnalyticsWidget;

    protected ?string $heading = 'Ventes projetées par mois';

    protected ?string $description = 'Projection sur le calendrier à venir ; en gris, les ventes réelles des mêmes mois l\'an dernier.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $forecast = app(DemandForecast::class);
        $history = app(SalesHistory::class);
        $settings = ForecastSettings::fromFilters($this->pageFilters);
        $today = CarbonImmutable::today();
        $end = SalesProjection::horizonEnd($this->pageFilters, $today);
        $ids = $history->variantIds($this->brandFilter());

        $shares = $forecast->shares($settings, $today);
        $share = array_sum(array_intersect_key($shares, array_flip($ids)));

        $projected = [];
        foreach ($forecast->shopDaily($settings, $today, $today, $end) as $date => $kg) {
            $projected[substr($date, 0, 7)] = ($projected[substr($date, 0, 7)] ?? 0) + $kg * $share;
        }

        $lastYear = $history->monthly($today->subYear(), $end->subYear(), $ids);

        return [
            'labels' => array_map(fn (string $month) => CarbonImmutable::createFromFormat('Y-m', $month)->locale('fr')->isoFormat('MMM YY'), array_keys($projected)),
            'datasets' => [
                [
                    'label' => 'Projection',
                    'data' => array_map(fn (float $kg) => round($kg), array_values($projected)),
                    'backgroundColor' => ChartColors::PRIMARY,
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'N-1 réel',
                    'data' => array_slice(array_values($lastYear), 0, count($projected)),
                    'backgroundColor' => ChartColors::alpha(ChartColors::MUTED, 0.55),
                    'borderRadius' => 4,
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
