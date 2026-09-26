<?php

namespace Modules\Allergenes;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Product;
use Modules\Allergenes\Filament\AllergenesPlugin;
use Modules\Allergenes\Filament\Extensions\ProductAllergensExtension;
use Modules\Allergenes\Models\Allergen;

class AllergenesServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): AllergenesPlugin
    {
        return AllergenesPlugin::make();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // $product->allergens : allergènes du produit, avec le niveau (contains / may_contain) dans le pivot.
        Product::resolveRelationUsing('allergens', fn ($product) => $product
            ->belongsToMany(Allergen::class, 'allergen_product')
            ->withPivot('type'));

        LunarPanel::extensions([
            EditProduct::class => ProductAllergensExtension::class,
        ]);
    }
}
