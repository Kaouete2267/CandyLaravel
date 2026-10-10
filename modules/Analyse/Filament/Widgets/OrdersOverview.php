<?php

namespace Modules\Analyse\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Analyse\Filament\Concerns\AnalyticsWidget;
use Modules\Analyse\Filament\Pages\UpcomingOrders;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\ForecastSettings;
use Modules\Analyse\Services\SalesHistory;
use Modules\Stock\Services\DeliverySchedule;

/** Une tuile par prochaine livraison : volume total suggéré et nombre de bonbons concernés. */
class OrdersOverview extends StatsOverviewWidget
{
    use AnalyticsWidget;

    protected function getColumns(): int
    {
        return UpcomingOrders::DELIVERIES;
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $orders = app(DemandForecast::class)->orders(ForecastSettings::fromFilters($this->pageFilters), CarbonImmutable::today(), UpcomingOrders::DELIVERIES);
        $rows = array_intersect_key($orders['rows'], array_flip(app(SalesHistory::class)->variantIds($this->brandFilter())));

        return collect($orders['deliveries'])->map(function (array $delivery, int $k) use ($rows) {
            $cartons = array_sum(array_map(fn (array $row) => $row['cartons'][$k], $rows));
            $kg = array_sum(array_map(fn (array $row) => $row['kg'][$k], $rows));
            $products = count(array_filter($rows, fn (array $row) => $row['cartons'][$k] > 0));

            return Stat::make($delivery['date']->locale('fr')->isoFormat('D MMMM YYYY'), number_format($cartons, 0, ',', ' ').' cartons')
                ->description($delivery['label'].' — '.number_format($kg, 0, ',', ' ')." kg, {$products} bonbon(s)")
                ->descriptionColor('gray')
                ->icon($delivery['kind'] === DeliverySchedule::ORDER ? Heroicon::OutlinedTruck : Heroicon::OutlinedArrowPath);
        })->all();
    }
}
