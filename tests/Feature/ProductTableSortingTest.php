<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Product;
use Modules\Support\ProductCreator;
use Tests\TestCase;

class ProductTableSortingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{oldest: Product, middle: Product, newest: Product} */
    private array $products;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);

        $this->products['oldest'] = ProductCreator::create(['name' => ['fr' => 'Crocodiles'], 'sku' => 'B-200']);
        $this->travel(1)->days();
        $this->products['middle'] = ProductCreator::create(['name' => ['fr' => 'Ananas'], 'sku' => 'C-300']);
        $this->travel(1)->days();
        $this->products['newest'] = ProductCreator::create(['name' => ['fr' => 'Bouteilles cola'], 'sku' => 'A-100']);
    }

    private function productList(): Testable
    {
        return Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')
            ->test(ListProducts::class)
            ->loadTable();
    }

    public function test_products_are_listed_newest_first_by_default(): void
    {
        $this->productList()
            ->assertCanSeeTableRecords([$this->products['newest'], $this->products['middle'], $this->products['oldest']], inOrder: true);
    }

    public function test_products_can_be_sorted_by_id(): void
    {
        $this->productList()
            ->sortTable('id')
            ->assertCanSeeTableRecords([$this->products['oldest'], $this->products['middle'], $this->products['newest']], inOrder: true);
    }

    public function test_products_can_be_sorted_by_translated_name(): void
    {
        $this->productList()
            ->sortTable('attribute_data.name')
            ->assertCanSeeTableRecords([$this->products['middle'], $this->products['newest'], $this->products['oldest']], inOrder: true);
    }

    public function test_products_can_be_sorted_by_variant_sku(): void
    {
        $this->productList()
            ->sortTable('variants.sku', 'desc')
            ->assertCanSeeTableRecords([$this->products['middle'], $this->products['oldest'], $this->products['newest']], inOrder: true);
    }

    public function test_products_can_be_sorted_by_status_brand_stock_and_type(): void
    {
        $list = $this->productList();

        foreach (['status', 'brand.name', 'variants_sum_stock', 'productType.name'] as $column) {
            $list->sortTable($column)->assertSuccessful();
        }
    }
}
