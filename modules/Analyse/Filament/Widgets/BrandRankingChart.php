<?php

namespace Modules\Analyse\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\ChartColors;
use Modules\Analyse\Support\Period;

/** Classement des marques de la période (kg vendus), avec la même période un an plus tôt. */
class BrandRankingChart extends ChartWidget
{
    use AnalyticsWidget;

    protected ?string $heading = 'Classement des marques';

    protected ?string $description = 'Kg vendus par marque sur la période ; en gris, la même période un an plus tôt.';

    protected ?string $maxHeight = '320px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->pageFilters);
        $previous = $period->previousYear();
        $colors = app(ChartColors::class)->brands();
        $brands = $history->brands();
        $ids = $history->variantIds($this->brandFilter());

        $current = array_filter($history->byBrand($period->from, $period->to, $ids));
        $before = $history->byBrand($previous->from, $previous->to, $ids);

        return [
            'labels' => array_map(fn (int|string $key) => $brands[$key] ?? $key, array_keys($current)),
            'datasets' => [
                [
                    'label' => 'Période',
                    'data' => array_values($current),
                    'backgroundColor' => array_map(fn (int|string $key) => $colors[$key] ?? ChartColors::OTHERS, array_keys($current)),
                    'borderRadius' => 4,
                    'barPercentage' => 0.9,
                    'categoryPercentage' => 0.8,
                ],
                [
                    'label' => 'N-1',
                    'data' => array_map(fn (int|string $key) => $before[$key] ?? 0, array_keys($current)),
                    'backgroundColor' => ChartColors::alpha(ChartColors::MUTED, 0.45),
                    'borderRadius' => 4,
                    'barPercentage' => 0.9,
                    'categoryPercentage' => 0.8,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['x' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(127,127,127,.15)']], 'y' => ['grid' => ['display' => false]]],
        ];
    }
}
