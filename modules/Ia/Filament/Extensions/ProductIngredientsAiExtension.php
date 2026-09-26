<?php

namespace Modules\Ia\Filament\Extensions;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Lunar\Base\FieldType;
use Lunar\FieldTypes\Text;
use Modules\Ia\Filament\Actions\ReviewIngredientsDiff;
use Modules\Ia\Filament\Actions\UpdateIngredientsWithAi;
use Modules\Ia\Filament\FieldTypes\TranslatedTextWithIngredientsAi;

/**
 * Ajoute, sur la fiche produit, l'aide « Normes de présentation » et deux boutons IA : « Mettre à jour avec
 * l'IA » (photo ou texte → traduction/normalisation → mise à jour de la fiche et des allergènes, tout en un
 * clic) et « Comparer avant / après » (modale de vérification avec avant/scan brut/après et changements
 * surlignés). Les champs cachés ci-dessous ne sont que la mémoire de travail entre les deux : rien n'est
 * affiché directement dans le formulaire, tout se voit dans la modale de comparaison.
 *
 * Le texte brut réellement passé à l'IA va dans l'attribut Lunar `ingredients_scan`, enregistré avec la fiche.
 * Cet attribut n'est volontairement pas rattaché au type de produit (voir BonbonBaseSeeder) : Lunar ne
 * l'affiche donc pas avec les autres, c'est cette section qui le rend en textarea — d'où la conversion
 * manuelle vers/depuis {@see Text}, que Lunar ne fait que pour les attributs qu'il affiche lui-même.
 *
 * La copie du bouton « Mettre à jour avec l'IA » à côté du champ « Ingrédients » est ajoutée ailleurs, par
 * {@see TranslatedTextWithIngredientsAi} : les champs d'attributs Lunar ne se modifient pas depuis ici.
 *
 * Reprend deux éléments de l'ancienne application (xampp/htdocs/test) jamais portés ici, le second remplacé
 * par ces boutons plutôt que l'ancien assistant manuel en 4 étapes.
 */
class ProductIngredientsAiExtension extends EditPageExtension
{
    public function extendForm(Schema $schema): Schema
    {
        $locales = array_keys(config('bonbon.locales'));

        return $schema->components([
            ...$schema->getComponents(withActions: true, withHidden: true),
            Section::make('Écriture assistée (IA)')
                ->description('Aide à rédiger le champ « Ingrédients » ci-dessus sous une forme compacte et normalisée, pour l\'étiquette imprimée du panneau.')
                ->collapsible()->collapsed()
                ->schema([
                    Html::make(new HtmlString(<<<'HTML'
                        <div class="text-sm space-y-2">
                            <p><b>Aides : normes de présentation.</b> Pour que la liste tienne sur un minimum d'espace, il est recommandé de :</p>
                            <ol class="list-decimal list-inside space-y-1">
                                <li>Regrouper les ingrédients par catégorie, triés par ordre alphabétique. Exemple : acides (citrique, malique, tartrique) — jamais "acides (acide citrique, acide malique)".</li>
                                <li>Ne pas inventer de catégorie qui n'existe pas sur l'emballage (ex. "antioxydants") : chaque ingrédient reste dans sa catégorie habituelle.</li>
                                <li>Mettre les arômes en avant-dernière position.</li>
                                <li>Mettre les identifiants des colorants (codes E) en dernière position — un seul colorant : « colorant : E133 » sans parenthèse.</li>
                                <li>Séparer les ingrédients par une virgule.</li>
                                <li>À l'inverse des colorants, les ingrédients eux-mêmes ne doivent jamais être réduits à un code E.</li>
                            </ol>
                            <p>Les allergènes ne se saisissent pas dans ce texte : ils se déclarent dans la section « Allergènes » ci-dessous.</p>
                        </div>
                        HTML
                    )),

                    Actions::make([
                        UpdateIngredientsWithAi::make(),
                        ReviewIngredientsDiff::make(),
                    ])->key('ingredientsAiActions'),

                    Textarea::make('attribute_data.ingredients_scan')
                        ->label('Ingrédients bruts (scan)')
                        ->helperText('Présent surtout à des fins de test : dernier texte envoyé à l\'IA (lu sur la photo ou saisi à la main), gardé tel quel. Il n\'est affiché nulle part ailleurs.')
                        ->rows(4)
                        ->formatStateUsing(fn ($state) => $state instanceof FieldType ? $state->getValue() : $state)
                        ->dehydrateStateUsing(fn (?string $state) => new Text($state ?? '')),

                    Hidden::make('ia_has_run')->dehydrated(false),
                    Hidden::make('ia_pending_raw')->dehydrated(false),
                    Hidden::make('ia_allergens_added')->dehydrated(false),
                    Hidden::make('ia_message')->dehydrated(false),
                    Hidden::make('ia_message_type')->dehydrated(false),
                    ...collect($locales)->map(fn (string $locale) => Hidden::make("ia_before_{$locale}")->dehydrated(false))->all(),
                ]),
        ]);
    }
}
