<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\ForecastSettings;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\ShopCalendar;
use Modules\Support\ProductCreator;
use Tests\TestCase;

/**
 * Historique construit à la main : la boutique vend exactement 30 kg × multiplicateur du jour
 * (bonbon A : 10, bonbon B : 20), donc 30 kg par unité d'affluence, et un tiers / deux tiers des ventes.
 */
class DemandForecastTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $today;

    /** @var array{a: ProductVariant, b: ProductVariant, new: ProductVariant} */
    private array $variants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);
        $this->today = CarbonImmutable::create(2026, 10, 5);

        $variant = fn (Product $product) => $product->variants()->first();
        $this->variants = [
            'a' => $variant(ProductCreator::create(['name' => ['fr' => 'Fraises'], 'sku' => 'A'])),
            'b' => $variant(ProductCreator::create(['name' => ['fr' => 'Oursons'], 'sku' => 'B'])),
            'new' => $variant(ProductCreator::create(['name' => ['fr' => 'Nouveauté'], 'sku' => 'N'])),
        ];

        // B est livré en cartons de 6 kg par son fournisseur principal ; A garde le carton par défaut (3 kg).
        ProductSupplier::create(['product_id' => $this->variants['b']->product_id, 'supplier_id' => Supplier::create(['name' => 'Grossiste'])->id, 'carton_kg' => 6, 'is_main' => true]);
    }

    /** Ventes quotidiennes de la boutique : $perUnit[variant] × multiplicateur, $recentFactor sur les 90 derniers jours. */
    private function seedSales(array $perUnit = ['a' => 10, 'b' => 20], float $recentFactor = 1.0): void
    {
        $calendar = new ShopCalendar;
        $rows = [];

        for ($day = $this->today->subYears(2); $day->lt($this->today); $day = $day->addDay()) {
            $multiplier = $calendar->multiplier($day);
            $factor = $day->gte($this->today->subDays(90)) ? $recentFactor : 1.0;

            foreach ($multiplier > 0 ? $perUnit : [] as $key => $kg) {
                $rows[] = [
                    'product_variant_id' => $this->variants[$key]->id,
                    'quantity' => -(int) round($kg * $multiplier * $factor),
                    'stock_after' => 0,
                    'reason' => StockReason::Sale->value,
                    'created_at' => $day->setTime(12, 0),
                    'updated_at' => $day->setTime(12, 0),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            StockMovement::insert($chunk);
        }
    }

    private function forecast(): DemandForecast
    {
        return app(DemandForecast::class);
    }

    public function test_projected_sales_follow_the_upcoming_calendar(): void
    {
        $this->seedSales();
        $daily = $this->forecast()->shopDaily(new ForecastSettings(ForecastSettings::LAST_YEAR), $this->today, $this->today, $this->today->addMonths(3));

        $this->assertSame(0.0, $daily['2026-12-25'], 'Fermé le 25 décembre');
        $this->assertSame(0.0, $daily['2026-10-12'], 'Fermé un lundi ordinaire');
        $this->assertEqualsWithDelta(30.0, $daily['2026-10-08'], 0.01, 'Jeudi ordinaire : 30 kg');
        $this->assertEqualsWithDelta(60.0, $daily['2026-10-11'], 0.01, 'Dimanche : ×2');
        $this->assertEqualsWithDelta(27.0, $daily['2026-12-03'], 0.01, 'Jeudi de décembre : ×0.9');
        $this->assertEqualsWithDelta(60.0, $daily['2026-11-11'], 0.01, 'Férié : ×2');
    }

    public function test_each_candy_gets_its_share_of_past_sales_and_a_new_candy_gets_none(): void
    {
        $this->seedSales();
        $shares = $this->forecast()->shares(new ForecastSettings, $this->today);

        $this->assertEqualsWithDelta(1 / 3, $shares[$this->variants['a']->id], 0.001);
        $this->assertEqualsWithDelta(2 / 3, $shares[$this->variants['b']->id], 0.001);
        $this->assertSame(0.0, $shares[$this->variants['new']->id]);
    }

    public function test_trend_correction_is_capped_and_only_applies_with_the_trend_method(): void
    {
        $this->seedSales(recentFactor: 2.0);

        $this->assertEqualsWithDelta(1.25, $this->forecast()->trend(new ForecastSettings(ForecastSettings::LAST_YEAR_TREND, trendCap: 0.25), $this->today), 0.001);
        $this->assertEqualsWithDelta(1.5, $this->forecast()->trend(new ForecastSettings(ForecastSettings::LAST_YEAR_TREND, trendCap: 0.5), $this->today), 0.001);
        $this->assertSame(1.0, $this->forecast()->trend(new ForecastSettings(ForecastSettings::LAST_YEAR), $this->today));
    }

    public function test_projection_flags_a_rupture_before_the_next_delivery(): void
    {
        $this->seedSales();
        $this->variants['a']->update(['stock' => 20]);
        $this->variants['b']->update(['stock' => 100000]);

        $rows = $this->forecast()->projection(new ForecastSettings(ForecastSettings::LAST_YEAR), $this->today, $this->today->addMonths(6));
        $a = $rows[$this->variants['a']->id];

        // A vend 10 kg un jeudi ordinaire : 20 kg tiennent jusqu'au samedi 10 octobre inclus.
        $this->assertSame('rupture', $a['status']);
        $this->assertSame('2026-10-11', $a['rupture_date']);
        $this->assertSame('2027-03-11', $a['next_delivery']);
        $this->assertSame('ok', $rows[$this->variants['b']->id]['status']);
        $this->assertSame('no_history', $rows[$this->variants['new']->id]['status']);
    }

    public function test_suggested_orders_cover_demand_until_the_following_delivery_in_whole_cartons(): void
    {
        $this->seedSales();
        $settings = new ForecastSettings(ForecastSettings::LAST_YEAR, margin: 0.0, safety: 0.0);
        $orders = $this->forecast()->orders($settings, $this->today, 2);
        $deliveries = [...$orders['deliveries'], $this->forecast()->schedule()->upcoming($this->today, 3)[2]];
        $daily = $this->forecast()->shopDaily($settings, $this->today, $this->today, $deliveries[2]['date']);
        $between = fn (CarbonImmutable $from, CarbonImmutable $to) => array_sum(array_filter($daily, fn (string $date) => $date >= $from->toDateString() && $date < $to->toDateString(), ARRAY_FILTER_USE_KEY));

        $b = $orders['rows'][$this->variants['b']->id];
        $leftover = max(0, $b['kg'][0] - 2 / 3 * $between($deliveries[0]['date'], $deliveries[1]['date']));
        $needed = 2 / 3 * $between($deliveries[1]['date'], $deliveries[2]['date']);

        // Stock de départ à 0 : le reliquat + la 2e livraison couvrent la demande jusqu'à la 3e, à moins d'un carton près.
        $this->assertSame(0, $b['kg'][1] % 6);
        $this->assertGreaterThanOrEqual($needed, $leftover + $b['kg'][1]);
        $this->assertLessThan($needed + 6, $leftover + $b['kg'][1]);
        $this->assertSame([0, 0], $orders['rows'][$this->variants['new']->id]['cartons']);
    }

    public function test_enough_stock_means_nothing_to_order(): void
    {
        $this->seedSales();
        $this->variants['a']->update(['stock' => 100000]);

        $orders = $this->forecast()->orders(new ForecastSettings, $this->today, 4);

        $this->assertSame(0, $orders['rows'][$this->variants['a']->id]['total_cartons']);
        $this->assertGreaterThan(0, $orders['rows'][$this->variants['b']->id]['total_cartons']);
    }
}
