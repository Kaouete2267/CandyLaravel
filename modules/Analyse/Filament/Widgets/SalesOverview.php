<?php

namespace Modules\Analyse\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\Period;

/** Chiffres clés de la période, comparés à la même période un an plus tôt. */
class SalesOverview extends StatsOverviewWidget
{
    use AnalyticsWidget;

    protected function getColumns(): int
    {
        return 4;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->pageFilters);
        $previous = $period->previousYear();
        $ids = $history->variantIds($this->brandFilter());

        $sold = array_sum($history->sales($period->from, $period->to, $ids));
        $soldBefore = array_sum($history->sales($previous->from, $previous->to, $ids));
        $openDays = $history->openDays($period->from, $period->to);
        $openDaysBefore = $history->openDays($previous->from, $previous->to);
        $perDay = $openDays > 0 ? $sold / $openDays : 0;
        $perDayBefore = $openDaysBefore > 0 ? $soldBefore / $openDaysBefore : 0;

        $monthly = array_values($history->monthly($period->from, $period->to, $ids));
        $top = $this->topProduct($history, $period, $ids);

        return [
            $this->compared('Kg vendus', $sold, $soldBefore)->chart($monthly)->chartColor('primary'),
            Stat::make('Jours d\'ouverture', $openDays)
                ->description("{$openDaysBefore} l'an dernier")
                ->descriptionColor('gray'),
            $this->compared('Kg par jour d\'ouverture', $perDay, $perDayBefore, decimals: 1),
            Stat::make('Meilleure vente', $top['name'] ?? '—')
                ->description($top ? $top['brand'].' — '.self::format($top['sold']).' kg' : 'Aucune vente sur la période')
                ->descriptionColor('gray'),
        ];
    }

    private function compared(string $label, float $value, float $before, int $decimals = 0): Stat
    {
        $stat = Stat::make($label, self::format($value, $decimals));

        if ($before <= 0) {
            return $stat->description('Pas de comparaison N-1')->descriptionColor('gray');
        }

        $change = ($value - $before) / $before * 100;

        return $stat
            ->description(sprintf('%+.1f %% vs N-1 (%s)', $change, self::format($before, $decimals)))
            ->descriptionIcon($change >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
            ->descriptionColor(match (true) {
                $change >= 5 => 'success',
                $change <= -5 => 'danger',
                default => 'gray',
            });
    }

    /**
     * @param  list<int>  $ids
     * @return array{name: string, brand: string, sold: int}|null
     */
    private function topProduct(SalesHistory $history, Period $period, array $ids): ?array
    {
        $sales = array_filter($history->sales($period->from, $period->to, $ids));
        if ($sales === []) {
            return null;
        }

        arsort($sales);
        $id = array_key_first($sales);
        $product = $history->products()[$id];

        return ['name' => $product['name'], 'brand' => $product['brand'], 'sold' => $sales[$id]];
    }

    private static function format(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
