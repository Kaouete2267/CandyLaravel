<?php

namespace Tests\Feature;

use Database\Seeders\BonbonBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Models\ProductVariant;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Filament\Pages\StockGrid;
use Modules\Stock\Models\StockMovement;
use Modules\Support\ProductCreator;
use Tests\TestCase;

class StockGridTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BonbonBaseSeeder::class);
        $product = ProductCreator::create(['name' => ['fr' => 'Fraises'], 'sku' => 'FR-1']);
        ProductSupplier::create(['product_id' => $product->id, 'supplier_id' => Supplier::create(['name' => 'Grossiste'])->id, 'bag_kg' => 2, 'carton_kg' => 6, 'is_main' => true]);
        $this->variant = $product->variants()->first();
    }

    private function overlay(): Testable
    {
        return Livewire::actingAs(Staff::factory()->create(['admin' => true]), 'staff')
            ->test(StockGrid::class)
            ->call('openOverlay', $this->variant->id);
    }

    public function test_a_receipt_is_entered_in_cartons_of_the_main_supplier(): void
    {
        $this->overlay()
            ->assertSet('unit', 'carton')
            ->assertSee('Carton (6 kg)')
            ->set('quantity', 2)
            ->call('submit');

        $this->assertSame(12, $this->variant->fresh()->stock);
        $this->assertDatabaseHas(StockMovement::class, ['quantity' => 12, 'input_unit' => 'carton', 'input_quantity' => 2]);
    }

    public function test_an_outgoing_movement_is_counted_in_bags_of_the_main_supplier(): void
    {
        $this->variant->update(['stock' => 10]);

        $this->overlay()
            ->call('setDirection', 'out')
            ->assertSet('unit', 'bag')
            ->assertSee('Sac (2 kg)')
            ->set('quantity', 3)
            ->call('submit');

        $this->assertSame(4, $this->variant->fresh()->stock);
        $this->assertDatabaseHas(StockMovement::class, ['quantity' => -6, 'input_unit' => 'bag', 'input_quantity' => 3]);
    }

    public function test_an_inventory_is_counted_in_kg(): void
    {
        $this->variant->update(['stock' => 10]);

        $this->overlay()
            ->call('setDirection', 'set')
            ->assertSet('unit', 'kg')
            ->set('quantity', 7)
            ->call('submit');

        $this->assertSame(7, $this->variant->fresh()->stock);
    }
}
