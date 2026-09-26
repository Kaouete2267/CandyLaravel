<?php

namespace Modules\Ia\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Modules\Allergenes\Enums\AllergenLevel;
use Modules\Allergenes\Models\Allergen;
use Modules\Ia\Services\GeminiException;
use Modules\Ia\Services\IngredientsWriter;
use Modules\Ia\Support\AllergenSync;
use Modules\Ia\Support\TextDiff;
use Modules\Support\Modules;

/**
 * Modale « Résultats de l'analyse par IA », ouverte automatiquement par {@see UpdateIngredientsWithAi} :
 * trois sections dans l'ordre du pipeline — la donnée qui a servi de source, ce que l'IA en a déduit, ce que
 * l'utilisateur s'apprête à valider — avec deux façons de corriger avant de valider :
 * - « Relancer » réécrit le texte source modifié ci-dessous (utile quand l'OCR s'est trompé) sans refermer
 *   la modale, pour comparer à nouveau avant de continuer ;
 * - le résultat final de chaque langue reste éditable directement, et « Valider » applique la version
 *   éventuellement corrigée à la fiche.
 *
 * Piège appris à la dure (voir {@see UpdateIngredientsWithAi}) : un `Get` utilisé dans un composant de
 * schéma (ex. {@see Html}) niché dans le `->schema()` de CETTE action ne relit pas de façon fiable les
 * champs de la page extérieure. Le HTML des suggestions/allergènes/message est donc entièrement précalculé
 * dans `->fillForm()` (seul point fiable pour lire la page extérieure) et déposé dans des champs cachés
 * PROPRES à cette action, que {@see Html} relit ensuite sans problème puisqu'il s'agit alors de simples
 * frères dans le même schéma.
 */
class ReviewIngredientsDiff
{
    public static function make(): Action
    {
        $locales = array_keys(config('bonbon.locales'));

        return Action::make('reviewIngredientsDiff')
            ->label('Comparer avant / après')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            // Seulement après un passage de l'IA sur cette page (c'est lui qui remplit les ia_before_*) : une
            // fiche rechargée avec un scan déjà enregistré n'a plus d'« avant » à comparer.
            ->visible(fn (Get $get) => (bool) $get('ia_has_run'))
            ->modalHeading('Résultats de l\'analyse par IA')
            ->modalDescription('Le texte source est illisible ou faux ? Corrigez-le et cliquez sur « Relancer ». Sinon, corrigez directement le résultat final ci-dessous puis validez.')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Valider')
            ->modalCancelActionLabel('Fermer')
            // Ouverte automatiquement par UpdateIngredientsWithAi (elle reste alors « empilée » sous
            // celle-ci) : fermer cette modale doit aussi refermer celle du dessous plutôt que la révéler vide.
            // S'applique aussi à ses propres remontages (voir `fail()`) : sans ça, la fermer n'en révélerait
            // qu'une autre, restée sur une erreur déjà corrigée.
            ->cancelParentActionsOnClose()
            ->fillForm(fn (Get $get) => [
                // Le texte que l'utilisateur vient de soumettre l'emporte sur le dernier texte source connu :
                // sinon, le remontage qui affiche une erreur de relance (voir `fail()`) effacerait sa saisie.
                'raw' => $get('ia_pending_raw') ?: $get('attribute_data.ingredients_scan'),
                ...collect($locales)->mapWithKeys(fn (string $locale) => [
                    "after_{$locale}" => $get("attribute_data.ingredients.{$locale}"),
                ])->all(),
                ...collect($locales)->mapWithKeys(fn (string $locale) => [
                    "suggestion_diff_{$locale}" => '<div class="text-sm leading-relaxed text-gray-950 dark:text-white">'
                        .TextDiff::html($get("ia_before_{$locale}"), $get("attribute_data.ingredients.{$locale}"))
                        .'</div>',
                ])->all(),
                'message' => $get('ia_message'),
                'message_type' => $get('ia_message_type'),
                'suggestions_allergens_html' => (string) view('ia::actions.allergens-summary', self::allergensViewData($get)),
                'allergens_added_note' => self::allergensAddedNote($get),
                'allergens_contains' => $get('allergens_contains') ?? [],
                'allergens_may_contain' => $get('allergens_may_contain') ?? [],
            ])
            ->schema([
                Html::make(fn (Get $get) => view('ia::actions.inline-message', [
                    'type' => $get('message_type'),
                    'message' => $get('message'),
                ])),

                Section::make('Données utilisées par l\'IA')
                    ->description('Scan brut (OCR) ou texte saisi à la main : la matière première de l\'analyse.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Textarea::make('raw')->label('Texte source')->rows(4),
                    ]),

                Section::make('Suggestions de l\'IA')
                    ->description('Ce que l\'IA propose pour chaque langue, changements par rapport à l\'ancien texte surlignés.')
                    ->icon(Heroicon::Sparkles)
                    ->schema([
                        Html::make(fn () => new HtmlString((string) view('ia::actions.diff-styles'))),
                        Tabs::make('suggestions')
                            ->tabs(collect($locales)->map(fn (string $locale) => Tab::make(strtoupper($locale))
                                ->schema([
                                    Html::make(fn (Get $get) => new HtmlString($get("suggestion_diff_{$locale}") ?? '')),
                                ]))->all()),
                        Html::make(fn (Get $get) => new HtmlString($get('suggestions_allergens_html') ?? '')),
                    ]),

                Section::make('Données validées par l\'utilisateur')
                    ->description('Corrigez si besoin avant de valider : c\'est ce texte qui sera appliqué à la fiche.')
                    ->icon(Heroicon::CheckBadge)
                    ->schema([
                        Tabs::make('validated')
                            ->tabs(collect($locales)->map(fn (string $locale) => Tab::make(strtoupper($locale))
                                ->schema([
                                    Textarea::make("after_{$locale}")->label('Ingrédients')->rows(6),
                                ]))->all()),
                        ...self::allergensFormSchema(),
                    ]),

                Hidden::make('message')->dehydrated(false),
                Hidden::make('message_type')->dehydrated(false),
                ...collect($locales)->map(fn (string $locale) => Hidden::make("suggestion_diff_{$locale}")->dehydrated(false))->all(),
                Hidden::make('suggestions_allergens_html')->dehydrated(false),
                Hidden::make('allergens_added_note')->dehydrated(false),
            ])
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('relaunch', arguments: ['relaunch' => true])
                    ->label('Relancer')
                    ->color('gray')
                    ->icon(Heroicon::ArrowPath),
            ])
            ->action(function (array $data, array $arguments, Set $set, Get $get, Action $action) use ($locales) {
                if ($arguments['relaunch'] ?? false) {
                    self::relaunch($data, $set, $get, $action);

                    return;
                }

                foreach ($locales as $locale) {
                    $set('attribute_data.ingredients.'.$locale, $data["after_{$locale}"] ?? '');
                }

                if (Modules::enabled('Allergenes')) {
                    $set('allergens_contains', $data['allergens_contains'] ?? []);
                    $set('allergens_may_contain', $data['allergens_may_contain'] ?? []);
                }

                $set('ia_message', null);
                $set('ia_message_type', null);
                $set('ia_pending_raw', null);
            });
    }

    private static function relaunch(array $data, Set $set, Get $get, Action $action): void
    {
        $raw = $data['raw'] ?? null;

        if (blank($raw)) {
            self::fail($set, $action, 'Rien à relancer : corrigez le texte source avant de relancer.', raw: $raw);

            return;
        }

        try {
            $result = app(IngredientsWriter::class)->rewrite($raw);
        } catch (GeminiException $e) {
            self::fail($set, $action, 'L\'IA n\'a pas pu répondre : '.$e->getMessage(), raw: $raw);

            return;
        }

        $set('ia_pending_raw', null);
        $set('attribute_data.ingredients_scan', $raw);

        foreach ($result['ingredients'] as $locale => $text) {
            $set('attribute_data.ingredients.'.$locale, $text);
        }

        $added = AllergenSync::apply($result['ingredients']['fr'] ?? '', $set, $get);
        $set('ia_allergens_added', $added);

        $set('ia_message_type', $result['notes'] ? 'success' : null);
        $set('ia_message', $result['notes']);

        // Remonte la même modale plutôt que de la fermer : son `->fillForm()` relit alors la page extérieure
        // et recalcule le HTML affiché à partir de l'état qu'on vient de mettre à jour, donnant l'effet d'un
        // rafraîchissement sur place (voir la note dans UpdateIngredientsWithAi sur ce mécanisme de Filament).
        $action->getLivewire()?->mountAction('reviewIngredientsDiff', context: [
            'schemaComponent' => 'form.ingredientsAiActions',
        ]);
    }

    private static function fail(Set $set, Action $action, string $message, ?string $raw = null): void
    {
        $set('ia_message_type', 'danger');
        $set('ia_message', $message);
        $set('ia_pending_raw', $raw);

        $action->getLivewire()?->mountAction('reviewIngredientsDiff', context: [
            'schemaComponent' => 'form.ingredientsAiActions',
        ]);
    }

    /** @return array{allergensAdded: array<int,string>, allergensContains: array<int,string>, allergensMayContain: array<int,string>, allergenesEnabled: bool} */
    private static function allergensViewData(Get $get): array
    {
        return [
            'allergensAdded' => $get('ia_allergens_added') ?? [],
            'allergensContains' => self::allergenNames($get('allergens_contains') ?? []),
            'allergensMayContain' => self::allergenNames($get('allergens_may_contain') ?? []),
            'allergenesEnabled' => Modules::enabled('Allergenes'),
        ];
    }

    /** @return array<int, string> */
    private static function allergenNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Allergen::whereIn('id', $ids)->get()->pluck('name')->all();
    }

    private static function allergensAddedNote(Get $get): string
    {
        $added = $get('ia_allergens_added') ?? [];

        if ($added === []) {
            return '';
        }

        return '<p class="text-sm text-gray-500 dark:text-gray-400">Ajoutés automatiquement par l\'IA : '.e(implode(', ', $added)).'</p>';
    }

    /**
     * Le même bloc « Allergènes » que celui de la fiche produit (voir ProductAllergensExtension), repris tel
     * quel plutôt que réduit à un résumé en lecture seule : c'est ici que l'utilisateur ajuste réellement la
     * sélection (déjà complétée par l'IA) avant de valider.
     *
     * @return array<int, Component>
     */
    private static function allergensFormSchema(): array
    {
        if (! Modules::enabled('Allergenes')) {
            return [];
        }

        $options = fn () => Allergen::orderBy('position')->get()->mapWithKeys(fn (Allergen $a) => [$a->id => $a->name])->all();

        return [
            Html::make(fn (Get $get) => new HtmlString($get('allergens_added_note') ?? '')),
            CheckboxList::make('allergens_contains')
                ->label(AllergenLevel::Contains->getLabel())
                ->options($options)
                ->columns(4)
                ->live(),
            CheckboxList::make('allergens_may_contain')
                ->label(AllergenLevel::MayContain->getLabel())
                ->options($options)
                ->disableOptionWhen(fn (string $value, Get $get) => in_array($value, $get('allergens_contains') ?? []))
                ->columns(4),
        ];
    }
}
