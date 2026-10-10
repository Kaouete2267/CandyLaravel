<?php

namespace Modules\Analyse\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\ChartColors;
use Modules\Analyse\Support\Period;

/** Ventes mensuelles de la période, avec la même période un an plus tôt en comparaison. */
class MonthlySalesChart extends ChartWidget
{
    use AnalyticsWidget;

    protected ?string $heading = 'Ventes mensuelles';

    protected ?string $description = 'Kg vendus par mois, comparés aux mêmes mois un an plus tôt.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->pageFilters);
        $previous = $period->previousYear();
        $ids = $history->variantIds($this->brandFilter());

        $current = $history->monthly($period->from, $period->to, $ids);
        $before = array_values($history->monthly($previous->from, $previous->to, $ids));

        return [
            'labels' => array_map(fn (string $month) => CarbonImmutable::createFromFormat('Y-m', $month)->locale('fr')->isoFormat('MMM YY'), array_keys($current)),
            'datasets' => [
                [
                    'label' => 'Période',
                    'data' => array_values($current),
                    'borderColor' => ChartColors::PRIMARY,
                    'backgroundColor' => ChartColors::PRIMARY,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'tension' => 0.25,
                ],
                [
                    'label' => 'N-1',
                    'data' => array_slice($before, 0, count($current)),
                    'borderColor' => ChartColors::MUTED,
                    'backgroundColor' => ChartColors::MUTED,
                    'borderDash' => [5, 4],
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'tension' => 0.25,
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
