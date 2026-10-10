<?php

namespace Modules\Stock;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\ProductVariant;
use Modules\Stock\Console\ClearStockCommand;
use Modules\Stock\Console\GenerateStockDatasetCommand;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Filament\StockPlugin;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\StockService;

class StockServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): StockPlugin
    {
        return StockPlugin::make();
    }

    public function register(): void
    {
        $this->app->singleton(StockService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'stock');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateStockDatasetCommand::class, ClearStockCommand::class]);
        }

        // Une correction manuelle du stock (fiche produit Lunar, import…) laisse aussi une trace dans le journal.
        ProductVariant::updating(function (ProductVariant $variant) {
            if (StockService::$recording || ! $variant->isDirty('stock')) {
                return;
            }

            $delta = (int) $variant->stock - (int) $variant->getOriginal('stock');

            StockMovement::create([
                'product_variant_id' => $variant->id,
                'staff_id' => auth('staff')->id(),
                'quantity' => $delta,
                'stock_after' => (int) $variant->stock,
                'reason' => StockReason::Inventory,
                'note' => 'Correction manuelle',
            ]);
        });
    }
}
