<?php

namespace Modules\Types;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Product;
use Modules\Types\Filament\Extensions\ProductCandyTypeExtension;
use Modules\Types\Filament\TypesPlugin;
use Modules\Types\Models\CandyType;

class TypesServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): TypesPlugin
    {
        return TypesPlugin::make();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // $product->candyType : type du bonbon (une collection de 0 ou 1 élément ; utiliser ->first()).
        Product::resolveRelationUsing('candyType', fn ($product) => $product
            ->belongsToMany(CandyType::class, 'candy_type_product'));

        LunarPanel::extensions([
            EditProduct::class => ProductCandyTypeExtension::class,
        ]);
    }
}
