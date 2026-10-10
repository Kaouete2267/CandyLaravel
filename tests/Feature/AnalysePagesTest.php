<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductVariant;
use Modules\Analyse\Filament\Pages\SalesAnalysis;
use Modules\Analyse\Filament\Pages\SalesProjection;
use Modules\Analyse\Filament\Pages\UpcomingOrders;
use Modules\Analyse\Filament\Widgets\BrandRankingChart;
use Modules\Analyse\Filament\Widgets\BrandShareChart;
use Modules\Analyse\Filament\Widgets\BrandTrendChart;
use Modules\Analyse\Filament\Widgets\MonthlySalesChart;
use Modules\Analyse\Filament\Widgets\OrdersOverview;
use Modules\Analyse\Filament\Widgets\ProjectedSalesChart;
use Modules\Analyse\Filament\Widgets\ProjectedStockChart;
use Modules\Analyse\Filament\Widgets\ProjectionOverview;
use Modules\Analyse\Filament\Widgets\SalesOverview;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Support\ProductCreator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalysePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);
        $this->travelTo('2026-10-05 10:00');

        foreach (['Haribo', 'Haribo', 'Astra', 'Vidal', null] as $i => $brand) {
            ProductCreator::create(['name' => ['fr' => "Bonbon {$i}"], 'sku' => "T-{$i}", 'brand' => $brand]);
        }

        $this->artisan('bonbon:stock-dataset', ['--years' => 2, '--seed' => 3])->assertSuccessful();
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    /** @return array<string, array{class-string}> */
    public static function widgets(): array
    {
        return collect([
            SalesOverview::class, MonthlySalesChart::class, BrandTrendChart::class, BrandShareChart::class, BrandRankingChart::class,
            ProjectionOverview::class, ProjectedSalesChart::class, ProjectedStockChart::class, OrdersOverview::class,
        ])->mapWithKeys(fn (string $widget) => [class_basename($widget) => [$widget]])->all();
    }

    /**
     * Les requêtes Livewire d'un widget (chargement différé, filtres) retrouvent sa classe par son nom ; sinon
     * Livewire répond 419 « This page has expired ». Livewire::test() enregistrant le composant, on vérifie avant.
     *
     * @param  class-string  $widget
     */
    #[DataProvider('widgets')]
    public function test_widget_can_be_resolved_by_name_for_livewire_requests(string $widget): void
    {
        $registry = app(ComponentRegistry::class);

        $this->assertSame($widget, $registry->getClass($registry->getName($widget)));
    }

    /** @param  class-string  $widget */
    #[DataProvider('widgets')]
    public function test_widget_renders_with_page_filters(string $widget): void
    {
        Livewire::test($widget, ['pageFilters' => ['period' => 'last_12', 'method' => 'last_year_trend', 'horizon' => 6]])
            ->assertOk();
    }

    public function test_sales_page_ranks_candies_then_brands(): void
    {
        Livewire::test(SalesAnalysis::class)
            ->assertOk()
            ->assertSee('Classement des bonbons')
            ->assertSee('Bonbon 0')
            ->set('filters.ranking', 'brands')
            ->assertSee('Classement des marques')
            ->assertSee('Haribo')
            ->assertSee('Sans marque');
    }

    public function test_projection_can_be_narrowed_to_ruptures_and_tight_stocks(): void
    {
        ProductVariant::where('sku', 'T-0')->update(['stock' => 0]);
        ProductVariant::where('sku', 'T-1')->update(['stock' => 100000]);

        Livewire::test(SalesProjection::class)
            ->assertOk()
            ->assertSee('Bonbon 0')
            ->assertSee('Bonbon 1')
            ->set('filters.only_risks', true)
            ->assertSee('Rupture avant livraison')
            ->assertSee('Bonbon 0')
            ->assertDontSee('Bonbon 1');
    }

    public function test_forecast_settings_are_shared_between_projection_and_orders(): void
    {
        Livewire::test(SalesProjection::class)->set('filters.method', 'average');

        Livewire::test(UpcomingOrders::class)->assertSet('filters.method', 'average');
    }

    public function test_orders_can_be_grouped_by_main_supplier_or_brand(): void
    {
        $product = ProductVariant::where('sku', 'T-0')->firstOrFail()->product;
        ProductSupplier::create(['product_id' => $product->id, 'supplier_id' => Supplier::create(['name' => 'Grossiste Test'])->id, 'is_main' => true]);

        Livewire::test(UpcomingOrders::class)
            ->set('tableGrouping', 'supplier')
            ->assertSee('Grossiste Test')
            ->assertSee('Sans fournisseur')
            ->assertSee('carton(s) à commander')
            ->set('tableGrouping', 'brand')
            ->assertSee('Haribo')
            ->assertSee('Sans marque');
    }

    public function test_sku_opens_the_product_edit_form(): void
    {
        $product = ProductVariant::where('sku', 'T-0')->firstOrFail()->product;
        $url = ProductResource::getUrl('edit', ['record' => $product]);

        Livewire::test(UpcomingOrders::class)->assertSee($url, false);
        Livewire::test(SalesProjection::class)->assertSee($url, false);
        Livewire::test(SalesAnalysis::class)->assertSee($url, false);
    }

    public function test_orders_can_be_exported_as_csv(): void
    {
        Livewire::test(UpcomingOrders::class)
            ->assertOk()
            ->assertSee('11/03/2027')
            ->callAction('export')
            ->assertFileDownloaded('commandes-2026-10-05.csv');
    }
}
