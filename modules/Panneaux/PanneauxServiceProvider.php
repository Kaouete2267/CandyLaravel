<?php

namespace Modules\Panneaux;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Product;
use Modules\Panneaux\Filament\Extensions\ProductPanelsExtension;
use Modules\Panneaux\Filament\PanneauxPlugin;
use Modules\Panneaux\Livewire\PanelBoardComponent;
use Modules\Panneaux\Models\Panel;

class PanneauxServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): PanneauxPlugin
    {
        return PanneauxPlugin::make();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'panneaux');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'panneaux');

        Livewire::component('panneaux-panel-board', PanelBoardComponent::class);

        // $product->panels : panneaux où figure le produit (pivot : active = en stock, dluo).
        Product::resolveRelationUsing('panels', fn ($product) => $product
            ->belongsToMany(Panel::class, 'panel_products')
            ->withPivot(['active', 'dluo']));

        LunarPanel::extensions([
            EditProduct::class => ProductPanelsExtension::class,
        ]);
    }
}
