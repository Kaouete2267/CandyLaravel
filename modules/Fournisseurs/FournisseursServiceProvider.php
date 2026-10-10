<?php

namespace Modules\Fournisseurs;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Fournisseurs\Console\GenerateSuppliersDatasetCommand;
use Modules\Fournisseurs\Filament\Extensions\ProductSuppliersExtension;
use Modules\Fournisseurs\Filament\FournisseursPlugin;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Fournisseurs\Services\SupplierCatalog;
use Modules\Stock\Services\StockService;
use Modules\Support\Modules;

/**
 * Fournisseurs : fiche (coordonnées, conditions, paramètres libres) et, pour chaque bonbon, ses fournisseurs avec
 * prix et conditionnement (contenu d'un carton), dont un fournisseur principal.
 */
class FournisseursServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): FournisseursPlugin
    {
        return FournisseursPlugin::make();
    }

    public function register(): void
    {
        $this->app->scoped(SupplierCatalog::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // $product->suppliers : fournisseurs du bonbon, avec prix, conditionnement et « principal » dans le pivot.
        Product::resolveRelationUsing('suppliers', fn (Product $product) => $product
            ->belongsToMany(Supplier::class, 'product_supplier')
            ->using(ProductSupplier::class)
            ->withPivot(['id', 'reference', 'bag_kg', 'carton_kg', 'carton_price', 'is_main'])
            ->withTimestamps());

        // Sac (vente) et carton (achat) viennent du fournisseur principal du bonbon (sinon réglages par défaut du stock).
        if (Modules::enabled('Stock')) {
            StockService::resolvePackagingUsing(fn (ProductVariant $variant) => app(SupplierCatalog::class)->packaging((int) $variant->product_id));
        }

        LunarPanel::extensions([
            EditProduct::class => ProductSuppliersExtension::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateSuppliersDatasetCommand::class]);
        }
    }
}
