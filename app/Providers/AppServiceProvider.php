<?php

namespace App\Providers;

use App\Lunar\FieldTypes\TranslatedText;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\AttributeData;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\FieldTypes\TranslatedText as TranslatedTextFieldType;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $plugins = [];

        foreach (config('modules.enabled', []) as $provider) {
            $this->app->register($provider);

            if (method_exists($provider, 'filamentPlugin') && ($plugin = $provider::filamentPlugin())) {
                $plugins[] = $plugin;
            }
        }

        // Le panneau d'administration est celui de Lunar ; les modules s'y greffent via leurs plugins Filament.
        LunarPanel::panel(fn (Panel $panel) => $panel
            ->path('admin')
            ->brandName(config('app.name'))
            ->plugins($plugins)
            // Thème Filament officiel (make:filament-theme) : compile le CSS de Filament via le pipeline
            // Vite/Tailwind de l'app elle-même plutôt que d'utiliser le bundle CSS pré-purgé du package, afin
            // que les classes Tailwind réutilisées dans nos propres vues (y compris les modules, voir les
            // directives @source de ce fichier) soient effectivement compilées.
            ->viteTheme('resources/css/filament/lunar/theme.css')
            // Certaines pages (ex. commandes Lunar) fixent leur propre largeur maximale ;
            // on neutralise ce plafond en CSS pour que le contenu (et donc les panneaux
            // latéraux droits) exploite toute la largeur disponible à l'écran.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>.fi-main{max-width:100%!important;}</style>'
            )
            // L'en-tête de page (titre + actions) statique sur un maximum de pages se fait par surcharge du
            // vrai template Blade — voir modules/Admin/resources/views/vendor/filament-panels/... et
            // Modules\Admin\AdminServiceProvider — pas par injection de CSS ici : le rendu est fiable et le
            // HTML reste inspectable/éditable normalement, au prix de devoir reporter ce changement si
            // Filament modifie ce fichier d'origine.
        )->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ajoute le rendu « textarea » aux attributs traduits (voir App\Lunar\FieldTypes\TranslatedText),
        // en plus des rendus « une ligne » / RichEditor déjà proposés par Lunar.
        AttributeData::registerFieldType(TranslatedTextFieldType::class, TranslatedText::class);
    }
}
