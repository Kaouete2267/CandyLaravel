<?php

namespace Modules\Analyse\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\ChartColors;
use Modules\Analyse\Support\Period;

/** Répartition des ventes de la période entre les marques (les plus petites regroupées en « Autres »). */
class BrandShareChart extends ChartWidget
{
    use AnalyticsWidget;

    protected ?string $heading = 'Répartition par marque';

    protected ?string $description = 'Part de chaque marque dans les kg vendus sur la période.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->pageFilters);
        $colors = app(ChartColors::class)->brands();
        $brands = $history->brands();

        $slices = [];
        $others = 0;
        foreach ($history->byBrand($period->from, $period->to, $history->variantIds($this->brandFilter())) as $brandKey => $kg) {
            if ($kg <= 0) {
                continue;
            }

            if (isset($colors[$brandKey])) {
                $slices[] = ['label' => $brands[$brandKey] ?? $brandKey, 'value' => $kg, 'color' => $colors[$brandKey]];
            } else {
                $others += $kg;
            }
        }

        if ($others > 0) {
            $slices[] = ['label' => ChartColors::OTHERS_LABEL, 'value' => $others, 'color' => ChartColors::OTHERS];
        }

        return [
            'labels' => array_column($slices, 'label'),
            'datasets' => [[
                'label' => 'Kg vendus',
                'data' => array_column($slices, 'value'),
                'backgroundColor' => array_column($slices, 'color'),
                'borderColor' => 'rgba(255,255,255,.9)',
                'borderWidth' => 2,
            ]],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '60%',
            'plugins' => ['legend' => ['display' => true, 'position' => 'right']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
