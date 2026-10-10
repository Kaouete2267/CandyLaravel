<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Lunar\Models\ProductVariant;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\ShopCalendar;
use Modules\Support\ProductCreator;
use Tests\TestCase;

class StockDatasetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);

        $supplier = Supplier::create(['name' => 'Grossiste']);

        foreach (range(1, 12) as $i) {
            $product = ProductCreator::create([
                'name' => ['fr' => "Bonbon {$i}"],
                'sku' => "T-{$i}",
                'brand' => $i <= 4 ? 'Haribo' : ($i <= 8 ? 'Astra' : null),
            ]);

            // Un bonbon sur trois arrive en cartons de 6 kg ; les autres gardent le carton par défaut (3 kg).
            if ($i % 3 === 0) {
                ProductSupplier::create(['product_id' => $product->id, 'supplier_id' => $supplier->id, 'carton_kg' => 6, 'is_main' => true]);
            }
        }
    }

    private function generate(int $years = 1, string $until = '2025-12-31', int $seed = 7): void
    {
        $this->artisan('bonbon:stock-dataset', ['--years' => $years, '--until' => $until, '--seed' => $seed])
            ->assertSuccessful();
    }

    /** @return Collection<int, StockMovement> */
    private function movements(?StockReason $reason = null): Collection
    {
        return StockMovement::query()
            ->when($reason, fn ($query) => $query->where('reason', $reason))
            ->orderBy('created_at')->orderBy('id')->get();
    }

    public function test_each_movement_chains_from_the_previous_stock_without_going_negative(): void
    {
        $this->generate();

        foreach ($this->movements()->groupBy('product_variant_id') as $variantId => $movements) {
            $stock = 0;
            foreach ($movements as $movement) {
                $stock += $movement->quantity;
                $this->assertSame($stock, $movement->stock_after);
                $this->assertGreaterThanOrEqual(0, $stock);
            }

            $this->assertSame($stock, ProductVariant::find($variantId)->stock);
        }
    }

    public function test_sales_never_happen_on_closed_days(): void
    {
        $this->generate();
        $calendar = new ShopCalendar(7);
        $saleDays = $this->movements(StockReason::Sale)->map(fn (StockMovement $m) => CarbonImmutable::parse($m->created_at)->startOfDay())->unique();

        $this->assertNotEmpty($saleDays);
        $this->assertNotContains('2025-12-25', $saleDays->map->toDateString());
        $this->assertNotContains('2025-01-01', $saleDays->map->toDateString());

        foreach ($saleDays as $day) {
            $summer = in_array($day->month, [7, 8], true);
            if (($day->isMonday() || $day->isWednesday()) && ! $summer) {
                $this->assertTrue($calendar->isPublicHoliday($day), "Vente un {$day->dayName} ordinaire : {$day->toDateString()}");
            }
        }
    }

    public function test_annual_closures_run_monday_to_next_friday_without_public_holiday_and_without_sales(): void
    {
        $this->generate();
        $calendar = new ShopCalendar(7);
        $saleDates = $this->movements(StockReason::Sale)->map(fn (StockMovement $m) => substr((string) $m->created_at, 0, 10));

        foreach ($calendar->closures(2025) as [$from, $to]) {
            $this->assertTrue($from->isMonday());
            $this->assertTrue($to->isFriday());
            $this->assertSame(11, (int) $from->diffInDays($to));

            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $this->assertFalse($calendar->isPublicHoliday($day), "Férié pendant la fermeture : {$day->toDateString()}");
            }
        }

        [$january, $spring] = $calendar->closures(2025);
        $this->assertSame(1, $january[0]->month);
        $this->assertContains($spring[0]->month, [5, 6]);

        $this->assertNotEmpty($saleDates);
        foreach ($saleDates as $date) {
            $this->assertFalse($calendar->isClosure(CarbonImmutable::parse($date)), "Vente pendant une fermeture : {$date}");
        }
    }

    public function test_deliveries_arrive_in_march_june_first_week_of_august_and_september(): void
    {
        $this->generate();
        $receiptDays = $this->movements(StockReason::Receipt)->map(fn (StockMovement $m) => CarbonImmutable::parse($m->created_at));

        $this->assertEqualsCanonicalizing([3, 6, 8, 9], $receiptDays->map->month->unique()->values()->all());
        $this->assertTrue($receiptDays->where('month', 8)->every(fn (CarbonImmutable $day) => $day->day <= 7));
        $this->assertTrue($receiptDays->every(fn (CarbonImmutable $day) => $day->isWeekday()));
    }

    public function test_receipts_are_recorded_in_whole_cartons(): void
    {
        $this->generate();

        $receipt = StockMovement::where('reason', StockReason::Receipt)
            ->whereHas('variant', fn ($query) => $query->where('sku', 'T-3'))->firstOrFail();

        $this->assertSame('carton', $receipt->input_unit);
        $this->assertSame($receipt->input_quantity * 6, $receipt->quantity);
    }

    public function test_stock_level_stays_close_from_one_year_to_the_next(): void
    {
        $this->generate(years: 3);
        $movements = $this->movements();

        $stockOn = fn (string $date) => $movements->filter(fn (StockMovement $m) => (string) $m->created_at <= "{$date} 23:59:59")
            ->groupBy('product_variant_id')->sum(fn (Collection $rows) => $rows->last()->stock_after);

        $levels = [$stockOn('2023-10-01'), $stockOn('2024-10-01'), $stockOn('2025-10-01')];

        $this->assertGreaterThan(0, min($levels));
        $this->assertLessThanOrEqual(1.3, max($levels) / min($levels), 'Stock au 1er octobre : '.implode(', ', $levels));
    }

    public function test_same_seed_reproduces_the_same_dataset(): void
    {
        $this->generate(seed: 42);
        $first = $this->movements()->map->only(['product_variant_id', 'quantity', 'created_at'])->all();

        $this->artisan('bonbon:stock-dataset', ['--years' => 1, '--until' => '2025-12-31', '--seed' => 42, '--fresh' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertEquals($first, $this->movements()->map->only(['product_variant_id', 'quantity', 'created_at'])->all());
    }

    public function test_generation_refuses_to_run_over_existing_movements_without_fresh(): void
    {
        $this->generate();
        $count = StockMovement::count();

        $this->artisan('bonbon:stock-dataset', ['--until' => '2025-12-31'])->assertFailed();

        $this->assertSame($count, StockMovement::count());
    }

    public function test_clear_command_erases_all_movements_and_resets_stock(): void
    {
        $this->generate();

        $this->artisan('bonbon:stock-clear', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, (int) ProductVariant::sum('stock'));
    }
}
