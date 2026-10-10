<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Product;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\Pages\EditSupplier;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\RelationManagers\ProductsRelationManager;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Models\StockSetting;
use Modules\Stock\Services\StockService;
use Modules\Support\ProductCreator;
use Tests\TestCase;

class SuppliersTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);
        $this->product = ProductCreator::create(['name' => ['fr' => 'Fraises'], 'sku' => 'FR-1']);
    }

    private function link(Supplier $supplier, array $attributes = []): ProductSupplier
    {
        return ProductSupplier::create(['product_id' => $this->product->id, 'supplier_id' => $supplier->id, ...$attributes]);
    }

    private function cartonKg(): int
    {
        return StockService::kgPerCarton($this->product->variants()->first());
    }

    public function test_carton_content_comes_from_the_main_supplier_then_the_supplier_then_the_default_setting(): void
    {
        $this->assertSame(StockSetting::DEFAULT_CARTON_KG, $this->cartonKg(), 'Sans fournisseur : réglage par défaut');

        $supplier = Supplier::create(['name' => 'Grossiste', 'default_carton_kg' => 5]);
        $link = $this->link($supplier, ['is_main' => true]);
        $this->assertSame(5, $this->cartonKg(), 'Conditionnement habituel du fournisseur');

        $link->update(['carton_kg' => 6]);
        $this->assertSame(6, $this->cartonKg(), 'Conditionnement propre au bonbon');
    }

    public function test_bag_weight_comes_from_the_main_supplier_then_the_supplier_then_the_default_setting(): void
    {
        $bagKg = fn () => StockService::kgPerBag($this->product->variants()->first());
        $this->assertSame(StockSetting::DEFAULT_BAG_KG, $bagKg(), 'Sans fournisseur : réglage par défaut');

        $link = $this->link(Supplier::create(['name' => 'Grossiste', 'default_bag_kg' => 2]), ['is_main' => true]);
        $this->assertSame(2, $bagKg(), 'Sac habituel du fournisseur');

        $link->update(['bag_kg' => 3]);
        $this->assertSame(3, $bagKg(), 'Sac propre au bonbon');
    }

    public function test_the_default_carton_setting_can_be_changed(): void
    {
        $this->assertSame(StockSetting::DEFAULT_CARTON_KG, $this->cartonKg());

        StockSetting::current()->update(['default_carton_kg' => 4]);

        $this->assertSame(4, $this->cartonKg());
    }

    public function test_a_candy_keeps_exactly_one_main_supplier(): void
    {
        $first = $this->link(Supplier::create(['name' => 'A']));
        $this->assertTrue($first->fresh()->is_main, 'Le premier fournisseur devient principal');

        $second = $this->link(Supplier::create(['name' => 'B']), ['is_main' => true]);
        $this->assertFalse($first->fresh()->is_main, 'Désigner un autre principal retire le titre au précédent');

        $second->delete();
        $this->assertTrue($first->fresh()->is_main, 'Supprimer le principal promeut le fournisseur restant');
    }

    public function test_starter_dataset_creates_three_suppliers_with_one_main_each_and_leaves_some_candies_without(): void
    {
        foreach (range(1, 40) as $i) {
            ProductCreator::create(['name' => ['fr' => "Bonbon {$i}"], 'sku' => "T-{$i}"]);
        }

        $this->artisan('bonbon:suppliers-dataset', ['--fresh' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertSame(3, Supplier::count());
        $mainsPerProduct = ProductSupplier::query()->selectRaw('product_id, SUM(is_main) as mains')->groupBy('product_id')->pluck('mains');
        $this->assertTrue($mainsPerProduct->every(fn ($mains) => (int) $mains === 1));
        $this->assertGreaterThan(0, Product::count() - $mainsPerProduct->count(), 'Quelques bonbons sans fournisseur');
        $this->assertGreaterThan(0, ProductSupplier::whereNotNull('carton_price')->count());
    }

    public function test_product_form_saves_its_suppliers_with_price_packaging_and_a_single_main(): void
    {
        [$a, $b] = [Supplier::create(['name' => 'A']), Supplier::create(['name' => 'B'])];

        Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')
            ->test(EditProduct::class, ['record' => $this->product->getKey()])
            ->fillForm(['supplier_links' => [
                ['supplier_id' => $a->id, 'reference' => 'A-1', 'bag_kg' => 2, 'carton_kg' => 6, 'carton_price' => 54.5, 'is_main' => false],
                ['supplier_id' => $b->id, 'reference' => null, 'bag_kg' => null, 'carton_kg' => null, 'carton_price' => 30, 'is_main' => true],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $links = ProductSupplier::where('product_id', $this->product->id)->get()->keyBy('supplier_id');
        $this->assertSame(2, $links[$a->id]->bag_kg);
        $this->assertSame(6, $links[$a->id]->carton_kg);
        $this->assertSame('54.50', $links[$a->id]->carton_price);
        $this->assertFalse($links[$a->id]->is_main);
        $this->assertTrue($links[$b->id]->is_main);
    }

    public function test_ticking_a_main_supplier_in_the_product_form_unticks_the_others(): void
    {
        [$a, $b] = [Supplier::create(['name' => 'A']), Supplier::create(['name' => 'B'])];
        $this->link($a, ['is_main' => true]);
        $this->link($b);

        $livewire = Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')
            ->test(EditProduct::class, ['record' => $this->product->getKey()]);

        [$firstKey, $secondKey] = array_keys($livewire->get('data.supplier_links'));

        $livewire->set("data.supplier_links.{$secondKey}.is_main", true)
            ->assertSet("data.supplier_links.{$firstKey}.is_main", false)
            ->assertSet("data.supplier_links.{$secondKey}.is_main", true);
    }

    public function test_a_carton_must_hold_a_whole_number_of_bags(): void
    {
        $supplier = Supplier::create(['name' => 'A']);

        Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')
            ->test(EditProduct::class, ['record' => $this->product->getKey()])
            ->fillForm(['supplier_links' => [
                ['supplier_id' => $supplier->id, 'bag_kg' => 2, 'carton_kg' => 5, 'is_main' => true],
            ]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertFalse(ProductSupplier::where('product_id', $this->product->id)->exists());
    }

    public function test_supplier_sheet_lists_its_candies(): void
    {
        $supplier = Supplier::create(['name' => 'Grossiste', 'settings' => ['Franco de port' => '250 €']]);
        $this->link($supplier, ['reference' => 'GR-42', 'carton_price' => 27]);

        $staff = Staff::factory()->create(['admin' => true]);

        Livewire::actingAs($staff, 'staff')
            ->test(EditSupplier::class, ['record' => $supplier->getKey()])
            ->assertOk()
            ->assertSchemaStateSet(['settings' => ['Franco de port' => '250 €']]);

        Livewire::actingAs($staff, 'staff')
            ->test(ProductsRelationManager::class, ['ownerRecord' => $supplier, 'pageClass' => EditSupplier::class])
            ->assertOk()
            ->assertCanSeeTableRecords([$this->product])
            ->assertSee('GR-42')
            ->assertSee(ProductResource::getUrl('edit', ['record' => $this->product]), false);
    }
}
