<?php

namespace Modules\Analyse\Services;

use Carbon\CarbonImmutable;
use Modules\Stock\Services\DeliverySchedule;
use Modules\Stock\Services\ShopCalendar;

/**
 * Prévision des ventes : les volumes de l'historique sont ramenés à une « vente par unité d'affluence »
 * (kg vendus ÷ somme des multiplicateurs des jours ouverts) mois par mois, puis rejoués sur le calendrier
 * à venir (jours ouverts, fermetures, dimanches, été…). Chaque bonbon reçoit sa part des ventes de la boutique
 * sur la période de référence.
 *
 * Méthodes : N-1 brute, N-1 × tendance récente (bornée), ou moyenne des N dernières années.
 */
class DemandForecast
{
    /** @var array<string, mixed> résultats déjà calculés dans la requête */
    private array $memo = [];

    public function __construct(private SalesHistory $history, private ShopCalendar $calendar) {}

    public function calendar(): ShopCalendar
    {
        return $this->calendar;
    }

    public function schedule(): DeliverySchedule
    {
        return new DeliverySchedule($this->calendar);
    }

    /**
     * Ventes attendues pour toute la boutique, par jour (dates incluses).
     *
     * @return array<string, float>
     */
    public function shopDaily(ForecastSettings $settings, CarbonImmutable $today, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->memo['daily|'.$settings->key().$today->toDateString().$from->toDateString().$to->toDateString()] ??= (function () use ($settings, $today, $from, $to) {
            $trend = $this->trend($settings, $today);
            $daily = [];

            for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
                $multiplier = $this->calendar->multiplier($day);
                $daily[$day->toDateString()] = $multiplier > 0
                    ? $this->referenceRate($settings, $today, $day->year, $day->month) * $multiplier * $trend
                    : 0.0;
            }

            return $daily;
        })();
    }

    /**
     * Part de chaque bonbon dans les ventes de la période de référence (somme = 1, 0 sans historique).
     *
     * @return array<int, float>
     */
    public function shares(ForecastSettings $settings, CarbonImmutable $today): array
    {
        return $this->memo['shares|'.$settings->referenceYears().$today->toDateString()] ??= (function () use ($settings, $today) {
            $sales = $this->history->sales($today->subYears($settings->referenceYears()), $today->subDay());
            $total = array_sum($sales);

            return $this->history->products()->keys()
                ->mapWithKeys(fn (int $id) => [$id => $total > 0 ? (float) ($sales[$id] ?? 0) / $total : 0.0])
                ->all();
        })();
    }

    /**
     * Correction de tendance : vente par unité d'affluence des $trendDays derniers jours comparée à la même
     * fenêtre un an plus tôt, bornée à ±$trendCap. Vaut 1 hors méthode « tendance » ou sans historique.
     */
    public function trend(ForecastSettings $settings, CarbonImmutable $today): float
    {
        if ($settings->method !== ForecastSettings::LAST_YEAR_TREND) {
            return 1.0;
        }

        $recent = $this->rate($today->subDays($settings->trendDays), $today->subDay());
        $before = $this->rate($today->subYear()->subDays($settings->trendDays), $today->subYear()->subDay());

        if (! $recent || ! $before) {
            return 1.0;
        }

        return max(1 - $settings->trendCap, min(1 + $settings->trendCap, $recent / $before));
    }

    /**
     * Projection par bonbon jusqu'à $end, sans nouvelle livraison : date de rupture, stock à la prochaine
     * livraison, état (rupture avant livraison, tension, ok), ventes N-1 sur la même période.
     *
     * @return array<int, array{id: int, name: string, sku: ?string, brand: string, product_id: int, brand_key: string, supplier: string, supplier_key: string, stock: int, per_bag: int, per_carton: int, share: float, projected: float, last_year: int, per_open_day: float, rupture_date: ?string, cover_days: ?int, next_delivery: ?string, stock_at_delivery: float, status: string, stockout_days_last_year: int}>
     */
    public function projection(ForecastSettings $settings, CarbonImmutable $today, CarbonImmutable $end): array
    {
        $deliveries = $this->schedule()->upcoming($today, 2);
        $next = $deliveries[0]['date'] ?? $end;
        $following = $deliveries[1]['date'] ?? $next->addMonths(3);
        $last = $end->max($following);

        $daily = $this->shopDaily($settings, $today, $today, $last);
        $dates = array_keys($daily);
        $cumulative = $this->cumulative($daily);
        $index = array_flip($dates);
        $at = fn (CarbonImmutable $day) => $index[$day->toDateString()] ?? count($dates);

        $horizon = $at($end->addDay());
        $openDays = count(array_filter(array_slice($daily, 0, $horizon), fn (float $kg) => $kg > 0));
        $shopProjected = $cumulative[$horizon];
        $shopUntilNext = $cumulative[$at($next)];
        $shopNextPeriod = $cumulative[$at($following)] - $shopUntilNext;

        $shares = $this->shares($settings, $today);
        $lastYear = $this->history->sales($today->subYear(), $end->subYear());
        $stockouts = $this->history->stockoutDays($today->subYear(), $today->subDay());
        $rows = [];

        foreach ($this->history->products() as $id => $product) {
            $share = $shares[$id] ?? 0.0;
            $stock = $product['stock'];

            // Premier jour où les ventes cumulées dépassent le stock.
            $rupture = null;
            if ($share > 0) {
                foreach ($dates as $i => $date) {
                    if ($i >= $horizon) {
                        break;
                    }
                    if ($share * $cumulative[$i + 1] > $stock) {
                        $rupture = $i;
                        break;
                    }
                }
            }

            $atDelivery = $stock - $share * $shopUntilNext;
            $safety = max(1, $settings->safety * sqrt($share * $shopNextPeriod));

            $status = match (true) {
                $share === 0.0 => 'no_history',
                $rupture !== null && $rupture < $at($next) => 'rupture',
                $atDelivery < $safety => 'tension',
                default => 'ok',
            };

            $rows[$id] = [
                ...$product,
                'share' => $share,
                'projected' => round($share * $shopProjected, 1),
                'last_year' => $lastYear[$id] ?? 0,
                'per_open_day' => $openDays > 0 ? round($share * $shopProjected / $openDays, 2) : 0.0,
                'rupture_date' => $rupture !== null ? $dates[$rupture] : null,
                'cover_days' => $rupture !== null ? count(array_filter(array_slice($daily, 0, $rupture), fn (float $kg) => $kg > 0)) : null,
                'next_delivery' => isset($deliveries[0]) ? $deliveries[0]['date']->toDateString() : null,
                'stock_at_delivery' => round($atDelivery, 1),
                'status' => $status,
                'stockout_days_last_year' => $stockouts[$id] ?? 0,
            ];
        }

        return $rows;
    }

    /**
     * Quantités suggérées pour les $count prochaines livraisons : chaque livraison remonte le stock à
     * « ventes attendues jusqu'à la livraison suivante × (1 + marge) + sécurité × √ventes », arrondi au carton.
     *
     * @return array{deliveries: list<array{date: CarbonImmutable, kind: string, label: string}>, rows: array<int, array{id: int, name: string, sku: ?string, brand: string, product_id: int, brand_key: string, supplier: string, supplier_key: string, stock: int, per_bag: int, per_carton: int, cartons: list<int>, kg: list<int>, total_cartons: int, total_kg: int}>}
     */
    public function orders(ForecastSettings $settings, CarbonImmutable $today, int $count = 4): array
    {
        return $this->memo['orders|'.$settings->key().$today->toDateString().$count] ??= (function () use ($settings, $today, $count) {
            $deliveries = $this->schedule()->upcoming($today, $count + 1);
            $daily = $this->shopDaily($settings, $today, $today, end($deliveries)['date']);
            $cumulative = $this->cumulative($daily);
            $index = array_flip(array_keys($daily));
            $at = fn (CarbonImmutable $day) => $index[$day->toDateString()] ?? count($daily);

            $shares = $this->shares($settings, $today);
            $rows = [];

            foreach ($this->history->products() as $id => $product) {
                $share = $shares[$id] ?? 0.0;
                $stock = (float) $product['stock'];
                $previous = 0;
                $row = [...$product, 'cartons' => [], 'kg' => []];

                for ($k = 0; $k < $count; $k++) {
                    $here = $at($deliveries[$k]['date']);
                    $stock = max(0.0, $stock - $share * ($cumulative[$here] - $cumulative[$previous]));
                    $expected = $share * ($cumulative[$at($deliveries[$k + 1]['date'])] - $cumulative[$here]);
                    $target = $share > 0 ? $expected * (1 + $settings->margin) + $settings->safety * sqrt($expected) : 0.0;

                    $cartons = (int) max(0, ceil(($target - $stock) / $product['per_carton']));
                    $row['cartons'][$k] = $cartons;
                    $row['kg'][$k] = $cartons * $product['per_carton'];
                    $stock += $row['kg'][$k];
                    $previous = $here;
                }

                $row['total_cartons'] = array_sum($row['cartons']);
                $row['total_kg'] = array_sum($row['kg']);
                $rows[$id] = $row;
            }

            return ['deliveries' => array_slice($deliveries, 0, $count), 'rows' => $rows];
        })();
    }

    /**
     * Stock total projeté jour par jour : sans nouvelle livraison, et avec les commandes suggérées.
     *
     * @param  list<int>  $variantIds
     * @return array<string, array{without: float, with: float}>
     */
    public function stockCurve(ForecastSettings $settings, CarbonImmutable $today, CarbonImmutable $end, array $variantIds): array
    {
        $daily = $this->shopDaily($settings, $today, $today, $end);
        $shares = $this->shares($settings, $today);
        $orders = $this->orders($settings, $today, 4);
        $products = $this->history->products();

        $arrivals = [];
        foreach ($orders['deliveries'] as $k => $delivery) {
            foreach ($variantIds as $id) {
                $arrivals[$delivery['date']->toDateString()][$id] = $orders['rows'][$id]['kg'][$k] ?? 0;
            }
        }

        $without = $with = collect($variantIds)->mapWithKeys(fn (int $id) => [$id => (float) ($products[$id]['stock'] ?? 0)])->all();
        $curve = [];

        foreach ($daily as $date => $shopKg) {
            foreach ($variantIds as $id) {
                $with[$id] += $arrivals[$date][$id] ?? 0;
                $sold = $shopKg * ($shares[$id] ?? 0);
                $without[$id] = max(0.0, $without[$id] - $sold);
                $with[$id] = max(0.0, $with[$id] - $sold);
            }

            $curve[$date] = ['without' => round(array_sum($without), 1), 'with' => round(array_sum($with), 1)];
        }

        return $curve;
    }

    /**
     * Vente par unité d'affluence du mois $year-$month, moyennée sur les années de référence
     * (mois complets passés uniquement), avec repli sur les 12 derniers mois.
     */
    private function referenceRate(ForecastSettings $settings, CarbonImmutable $today, int $year, int $month): float
    {
        return $this->memo['ref|'.$settings->referenceYears().$today->toDateString()."{$year}-{$month}"] ??= (function () use ($settings, $today, $year, $month) {
            $rates = [];

            for ($back = 1; count($rates) < $settings->referenceYears() && $back <= $settings->referenceYears() + 1; $back++) {
                $start = CarbonImmutable::create($year - $back, $month, 1);
                if ($start->endOfMonth()->gte($today)) {
                    continue;
                }

                if (($rate = $this->rate($start, $start->endOfMonth())) !== null) {
                    $rates[] = $rate;
                }
            }

            return $rates !== [] ? array_sum($rates) / count($rates) : $this->fallbackRate($today);
        })();
    }

    private function fallbackRate(CarbonImmutable $today): float
    {
        return $this->memo['fallback|'.$today->toDateString()] ??= $this->rate($today->subYear(), $today->subDay()) ?? 0.0;
    }

    /**
     * Kg vendus par unité d'affluence sur une période passée : seuls comptent les jours où la boutique a vendu
     * (les vraies fermetures se lisent dans l'historique). Null si aucune vente.
     */
    private function rate(CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        $shop = $this->history->shopDaily();
        $sales = 0;
        $affluence = 0.0;

        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $kg = $shop[$day->toDateString()] ?? 0;

            if ($kg > 0) {
                $sales += $kg;
                $affluence += $this->calendar->multiplier($day, withClosures: false) ?: 1.0;
            }
        }

        return $affluence > 0 ? $sales / $affluence : null;
    }

    /**
     * Cumul des ventes : $cumulative[$i] = ventes des jours d'index < $i.
     *
     * @param  array<string, float>  $daily
     * @return list<float>
     */
    private function cumulative(array $daily): array
    {
        $cumulative = [0.0];
        foreach (array_values($daily) as $i => $kg) {
            $cumulative[$i + 1] = $cumulative[$i] + $kg;
        }

        return $cumulative;
    }
}
