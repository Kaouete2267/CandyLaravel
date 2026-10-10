<?php

namespace Modules\Analyse\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\ChartColors;
use Modules\Analyse\Support\Period;

/** Évolution mensuelle des 4 marques les plus vendues de la période (une couleur fixe par marque). */
class BrandTrendChart extends ChartWidget
{
    use AnalyticsWidget;

    public const MAX_BRANDS = 4;

    protected ?string $heading = 'Évolution des principales marques';

    protected ?string $description = 'Kg vendus par mois pour les 4 marques les plus vendues sur la période.';

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->pageFilters);
        $colors = app(ChartColors::class)->brands();
        $brands = $history->brands();
        $ids = $history->variantIds($this->brandFilter());

        $top = array_slice(array_keys(array_filter($history->byBrand($period->from, $period->to, $ids))), 0, self::MAX_BRANDS);
        $labels = [];
        $datasets = [];

        foreach ($top as $brandKey) {
            $monthly = $history->monthly($period->from, $period->to, $history->variantIds((string) $brandKey));
            $labels = array_keys($monthly);
            $color = $colors[$brandKey] ?? ChartColors::OTHERS;

            $datasets[] = [
                'label' => $brands[$brandKey] ?? $brandKey,
                'data' => array_values($monthly),
                'borderColor' => $color,
                'backgroundColor' => $color,
                'borderWidth' => 2,
                'pointRadius' => 3,
                'tension' => 0.25,
            ];
        }

        return [
            'labels' => array_map(fn (string $month) => CarbonImmutable::createFromFormat('Y-m', $month)->locale('fr')->isoFormat('MMM YY'), $labels),
            'datasets' => $datasets,
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
