<?php

namespace Modules\Stock\Services\Dataset;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductVariant;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\DeliverySchedule;
use Modules\Stock\Services\ShopCalendar;
use Modules\Stock\Services\StockService;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Génère un historique de stock réaliste (jeu de données pour l'analyse et la prévision).
 *
 * Ventes : un jour d'ouverture ordinaire, la boutique vend environ DAILY_KG kg (×2 / ×0.9 selon le calendrier),
 * répartis selon la popularité de chaque bonbon (quelques best-sellers, Haribo un peu plus demandé), vendus en sacs
 * entiers (poids d'un sac : conditionnement de vente du fournisseur principal).
 * Livraisons : la commande préparée en septembre arrive en mars et en juin, puis deux réassorts
 * (première semaine d'août, septembre pour tenir jusqu'en mars). Chaque livraison complète le stock
 * de quoi tenir jusqu'à la suivante, avec une marge : le stock revient au même niveau d'une année sur l'autre.
 */
class StockDatasetGenerator
{
    /** Kg vendus un jour ordinaire (multiplicateur 1), pour toute la boutique. */
    public const DAILY_KG = 10;

    /** @var array<string, float> multiplicateur de demande par fournisseur (nom de marque en minuscules) */
    public const SUPPLIER_MULTIPLIERS = ['haribo' => 1.15];

    public const BEST_SELLERS = 5;

    public const BEST_SELLER_BOOST = 10;

    /** Marge de sécurité appliquée aux commandes. */
    public const MARGIN = 0.15;

    private Randomizer $random;

    private ShopCalendar $calendar;

    /**
     * @return array{products: int, movements: int, sales: int, receipts: int, sold_kg: int, received_kg: int}
     */
    public function generate(CarbonImmutable $from, CarbonImmutable $until, int $seed): array
    {
        $this->random = new Randomizer(new Mt19937($seed));
        $this->calendar = new ShopCalendar($seed);

        $from = $from->startOfDay();
        $until = $until->startOfDay();
        $variants = ProductVariant::with('product.brand')->orderBy('id')->get();
        $stats = ['products' => $variants->count(), 'movements' => 0, 'sales' => 0, 'receipts' => 0, 'sold_kg' => 0, 'received_kg' => 0];

        if ($variants->isEmpty() || $from->gt($until)) {
            return $stats;
        }

        $weights = $this->popularity($variants);
        $bagKg = $variants->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => StockService::kgPerBag($variant)])->all();
        $deliveries = $this->deliveries($from->year, $until->year + 1);
        $traffic = $this->traffic($from, $until->addYear());
        $staffIds = Staff::pluck('id')->all();

        $rows = [];
        $stock = [];
        $row = function (ProductVariant $variant, CarbonImmutable $at, int $kg, StockReason $reason, ?string $note = null, ?string $unit = null, ?int $inputQuantity = null) use (&$rows, &$stock, $staffIds): void {
            $stock[$variant->id] = ($stock[$variant->id] ?? 0) + $kg;
            $rows[] = [
                'product_variant_id' => $variant->id,
                'staff_id' => $staffIds === [] ? null : $staffIds[$this->random->getInt(0, count($staffIds) - 1)],
                'quantity' => $kg,
                'stock_after' => $stock[$variant->id],
                'reason' => $reason->value,
                'input_unit' => $unit,
                'input_quantity' => $inputQuantity,
                'note' => $note,
                'created_at' => $at->toDateTimeString(),
                'updated_at' => $at->toDateTimeString(),
            ];
        };

        // Inventaire d'ouverture : de quoi tenir jusqu'à la première livraison.
        $expected = $this->expectedKg($traffic, $from, $this->nextDelivery($deliveries, $from));
        foreach ($variants as $variant) {
            $row($variant, $from->setTime(8, 0), $this->target($weights[$variant->id], $expected), StockReason::Inventory, 'Inventaire d\'ouverture');
        }

        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            if ($note = $deliveries[$day->toDateString()] ?? null) {
                $expected = $this->expectedKg($traffic, $day, $this->nextDelivery($deliveries, $day));

                foreach ($variants as $variant) {
                    $perCarton = StockService::kgPerCarton($variant);
                    $missing = $this->target($weights[$variant->id], $expected) - $stock[$variant->id];

                    if ($missing > 0) {
                        $cartons = (int) ceil($missing / $perCarton);
                        $row($variant, $day->setTime(8, $this->random->getInt(0, 59)), $cartons * $perCarton, StockReason::Receipt, $note, StockService::UNIT_CARTON, $cartons);
                        $stats['receipts']++;
                        $stats['received_kg'] += $cartons * $perCarton;
                    }
                }
            }

            $dailyKg = $traffic[$day->toDateString()] ?? 0;
            if ($dailyKg <= 0) {
                continue;
            }

            // Les ventes se comptent en sacs entiers (conditionnement de vente du fournisseur principal).
            foreach ($variants as $variant) {
                $perBag = $bagKg[$variant->id];
                $bags = min(intdiv($stock[$variant->id], $perBag), $this->poisson($dailyKg * $weights[$variant->id] / $perBag));

                if ($bags > 0) {
                    $row($variant, $day->setTime($this->random->getInt(10, 18), $this->random->getInt(0, 59)), -$bags * $perBag, StockReason::Sale, null, StockService::UNIT_BAG, $bags);
                    $stats['sales']++;
                    $stats['sold_kg'] += $bags * $perBag;
                }
            }
        }

        usort($rows, fn (array $a, array $b) => [$a['created_at'], $a['product_variant_id']] <=> [$b['created_at'], $b['product_variant_id']]);

        DB::transaction(function () use ($rows, $stock) {
            foreach (array_chunk($rows, 500) as $chunk) {
                StockMovement::insert($chunk);
            }

            // Mise à jour en masse : ne passe pas par l'observateur qui journalise les corrections manuelles.
            foreach ($stock as $variantId => $kg) {
                ProductVariant::withTrashed()->whereKey($variantId)->update(['stock' => $kg]);
            }
        });

        $stats['movements'] = count($rows);

        return $stats;
    }

    /**
     * Part de chaque bonbon dans les ventes du jour (somme = 1).
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return array<int, float>
     */
    private function popularity(Collection $variants): array
    {
        $raw = [];
        $suppliers = [];
        foreach ($variants as $variant) {
            $gaussian = sqrt(-2 * log($this->random->getFloat(PHP_FLOAT_EPSILON, 1))) * cos(2 * M_PI * $this->random->getFloat(0, 1));
            $suppliers[$variant->id] = self::SUPPLIER_MULTIPLIERS[mb_strtolower(trim((string) $variant->product?->brand?->name))] ?? 1.0;
            $raw[$variant->id] = min(3.0, exp(0.6 * $gaussian)) * $suppliers[$variant->id];
        }

        foreach ($this->random->pickArrayKeys($raw, min(self::BEST_SELLERS, max(1, intdiv(count($raw), 10)))) as $id) {
            $raw[$id] = self::BEST_SELLER_BOOST * $this->random->getFloat(0.8, 1.2) * $suppliers[$id];
        }

        $total = array_sum($raw);

        return array_map(fn (float $w) => $w / $total, $raw);
    }

    /**
     * Kg attendus par jour pour toute la boutique (0 les jours de fermeture).
     *
     * @return array<string, float>
     */
    private function traffic(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $traffic = [];
        for ($day = $from; $day->lte($until); $day = $day->addDay()) {
            $traffic[$day->toDateString()] = self::DAILY_KG * $this->calendar->multiplier($day);
        }

        return $traffic;
    }

    /**
     * Dates de livraison (Y-m-d => note), en semaine, hors jour férié et fermeture.
     *
     * @return array<string, string>
     */
    private function deliveries(int $fromYear, int $toYear): array
    {
        $schedule = new DeliverySchedule($this->calendar);
        $deliveries = [];

        foreach ($schedule->between(CarbonImmutable::create($fromYear), CarbonImmutable::create($toYear, 12, 31), $this->random) as $delivery) {
            $deliveries[$delivery['date']->toDateString()] = $delivery['label'];
        }

        ksort($deliveries);

        return $deliveries;
    }

    /** @param  array<string, string>  $deliveries */
    private function nextDelivery(array $deliveries, CarbonImmutable $after): CarbonImmutable
    {
        foreach (array_keys($deliveries) as $date) {
            if ($date > $after->toDateString()) {
                return CarbonImmutable::parse($date);
            }
        }

        return $after->addMonths(6);
    }

    /**
     * Kg attendus pour toute la boutique entre deux dates (fin exclue).
     *
     * @param  array<string, float>  $traffic
     */
    private function expectedKg(array $traffic, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $expected = 0.0;
        for ($day = $from; $day->lt($to); $day = $day->addDay()) {
            $expected += $traffic[$day->toDateString()] ?? 0;
        }

        return $expected;
    }

    /**
     * Stock visé pour un bonbon : sa part des ventes attendues jusqu'à la livraison suivante, plus la marge
     * et un stock de sécurité (deux écarts-types de la demande) pour éviter les ruptures des petits volumes.
     */
    private function target(float $weight, float $expectedKg): int
    {
        $expected = $weight * $expectedKg;

        return max(1, (int) ceil($expected * (1 + self::MARGIN) + 2 * sqrt($expected)));
    }

    /** Tirage de Poisson (Knuth) : sacs vendus dans la journée. */
    private function poisson(float $lambda): int
    {
        $limit = exp(-$lambda);
        $count = 0;
        $product = $this->random->getFloat(0, 1);

        while ($product > $limit) {
            $count++;
            $product *= $this->random->getFloat(0, 1);
        }

        return $count;
    }
}
