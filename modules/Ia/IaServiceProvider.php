<?php

namespace Modules\Ia;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Support\Facades\AttributeData;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\FieldTypes\TranslatedText;
use Modules\Ia\Filament\Extensions\ProductIngredientsAiExtension;
use Modules\Ia\Filament\FieldTypes\TranslatedTextWithIngredientsAi;
use Modules\Ia\Filament\IaPlugin;
use Modules\Ia\Services\GeminiClient;

class IaServiceProvider extends ServiceProvider
{
    public static function filamentPlugin(): IaPlugin
    {
        return IaPlugin::make();
    }

    public function register(): void
    {
        $this->app->singleton(GeminiClient::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'ia');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        LunarPanel::extensions([
            EditProduct::class => ProductIngredientsAiExtension::class,
        ]);

        // Après le démarrage complet : AppServiceProvider enregistre déjà sa propre version de ce type de
        // champ (que celle-ci prolonge) et, démarré après les modules, l'écraserait sinon.
        $this->app->booted(fn () => AttributeData::registerFieldType(TranslatedText::class, TranslatedTextWithIngredientsAi::class));
    }
}
