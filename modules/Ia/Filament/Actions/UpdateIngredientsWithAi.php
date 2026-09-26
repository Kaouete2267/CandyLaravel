<?php

namespace Modules\Ia\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Modules\Ia\Services\GeminiException;
use Modules\Ia\Services\ImagePayload;
use Modules\Ia\Services\IngredientsScanner;
use Modules\Ia\Services\IngredientsWriter;
use Modules\Ia\Support\AllergenSync;

/**
 * Un seul clic fait tout : lit (OCR) une photo de l'emballage — dans n'importe quelle langue — ou reprend le
 * texte saisi/collé à la main, le traduit et le normalise dans chaque langue du site, met à jour les vrais
 * champs « Ingrédients » de la fiche, et fait évoluer la liste des allergènes en conséquence. Avant
 * d'écraser un champ, sa valeur est mise de côté (ia_before_*) pour que {@see ReviewIngredientsDiff} puisse
 * afficher la comparaison avant/scan brut/après dans une modale dédiée.
 *
 * Les messages (erreurs, avertissements) s'affichent DANS la modale plutôt qu'en notification flottante.
 * Piège appris à la dure : un `Get` utilisé dans un composant de schéma (ex. {@see Html}) niché à l'intérieur
 * du `->schema()` de CETTE action ne relit pas de façon fiable les champs de la page extérieure (même de
 * simples champs cachés) — seuls les closures de premier niveau de l'action (`->action()`, `->fillForm()`)
 * le font correctement. Le message est donc écrit sur la page extérieure (ia_message/ia_message_type) par
 * `->action()`, puis SEUL `->fillForm()` (rappelé via un remontage de l'action, voir plus bas) le recopie
 * dans un champ `message`/`message_type` propre à cette action, que {@see Html} relit alors sans problème
 * puisqu'il s'agit cette fois d'un simple frère dans le même schéma.
 */
class UpdateIngredientsWithAi
{
    private const TMP_DIR = 'ia-tmp';

    public static function make(): Action
    {
        return Action::make('updateIngredientsWithAi')
            ->label('Mettre à jour avec l\'IA')
            ->icon(Heroicon::Sparkles)
            ->color('gray')
            ->modalHeading('Mettre à jour les ingrédients avec l\'IA')
            ->modalDescription('Une photo de l\'emballage (l\'IA lit la liste même dans une langue étrangère et la traduit), et/ou un texte à traiter directement. La fiche et les allergènes sont mis à jour tout de suite, puis la comparaison s\'ouvre automatiquement.')
            ->modalSubmitActionLabel('Lancer')
            // Referme aussi les instances d'elle-même empilées par un remontage sur erreur (voir plus bas) :
            // sans ça, fermer la modale n'en révélerait qu'une autre, restée sur une erreur déjà corrigée.
            ->cancelParentActionsOnClose()
            ->fillForm(fn (Get $get) => [
                // Le texte que l'utilisateur vient de soumettre l'emporte sur la valeur du produit : sinon,
                // le remontage qui affiche l'erreur (voir `fail()`) effacerait ce qu'il venait de taper.
                'raw' => $get('ia_pending_raw') ?: ($get('attribute_data.ingredients.fr') ?: ''),
                'message' => $get('ia_message'),
                'message_type' => $get('ia_message_type'),
            ])
            ->schema([
                Html::make(fn (Get $get) => view('ia::actions.inline-message', [
                    'type' => $get('message_type'),
                    'message' => $get('message'),
                ])),
                FileUpload::make('photo')
                    ->label('Photo (optionnelle)')
                    ->image()
                    ->maxSize(8192)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->disk('local')
                    ->directory(self::TMP_DIR)
                    ->visibility('private'),
                Textarea::make('raw')
                    ->label('Texte à traiter (si pas de photo, ou à corriger)')
                    ->rows(5),
                Hidden::make('message')->dehydrated(false),
                Hidden::make('message_type')->dehydrated(false),
            ])
            ->action(function (array $data, Set $set, Get $get, Action $action) {
                $raw = $data['raw'] ?? null;

                if (filled($data['photo'] ?? null)) {
                    try {
                        $image = ImagePayload::fromPath(Storage::disk('local')->path($data['photo']));
                        $scan = app(IngredientsScanner::class)->scan([$image]);
                    } catch (GeminiException $e) {
                        self::fail($set, $action, 'L\'IA n\'a pas pu lire la photo : '.$e->getMessage(), raw: $raw);

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['photo']);
                    }

                    $raw = $scan['raw'];

                    if ($raw === '') {
                        self::fail($set, $action, 'Liste d\'ingrédients illisible. '.($scan['notes'] ?? 'Réessayez avec une photo plus nette, ou saisissez le texte à la main.'), type: 'warning', raw: $data['raw'] ?? null);

                        return;
                    }
                }

                if (blank($raw)) {
                    self::fail($set, $action, 'Rien à traiter : ajoutez une photo ou saisissez une liste d\'ingrédients.', raw: $raw);

                    return;
                }

                try {
                    $result = app(IngredientsWriter::class)->rewrite($raw);
                } catch (GeminiException $e) {
                    self::fail($set, $action, 'L\'IA n\'a pas pu répondre : '.$e->getMessage(), raw: $raw);

                    return;
                }

                $set('ia_pending_raw', null);

                $locales = array_keys(config('bonbon.locales'));
                foreach ($locales as $locale) {
                    $set("ia_before_{$locale}", $get("attribute_data.ingredients.{$locale}") ?: '');
                }
                $set('attribute_data.ingredients_scan', $raw);
                $set('ia_has_run', true);

                foreach ($result['ingredients'] as $locale => $text) {
                    $set('attribute_data.ingredients.'.$locale, $text);
                }

                $added = AllergenSync::apply($result['ingredients']['fr'] ?? '', $set, $get);
                $set('ia_allergens_added', $added);

                // Le message (s'il y en a un) suit dans la modale de comparaison qui s'ouvre juste après : son
                // propre `->fillForm()` le relit sur la page extérieure au moment de s'ouvrir.
                $set('ia_message_type', $result['notes'] ? 'success' : null);
                $set('ia_message', $result['notes']);

                // Ouvre directement la modale de comparaison à la place de fermer celle-ci : depuis le point
                // de vue de l'action en cours, monter une autre action la remplace sur la pile plutôt que de
                // se fermer derrière elle (voir InteractsWithActions::mountAction()).
                $action->getLivewire()?->mountAction('reviewIngredientsDiff', context: [
                    'schemaComponent' => 'form.ingredientsAiActions',
                ]);
            });
    }

    /**
     * Écrit le message sur la page extérieure puis remonte cette même action : c'est ce remontage qui fait
     * rejouer `->fillForm()` (fiable) et rafraîchit ainsi le message affiché dans la modale — un simple
     * `$action->halt()` laisserait la modale ouverte mais avec l'ancien contenu, jamais rafraîchi.
     */
    private static function fail(Set $set, Action $action, string $message, string $type = 'danger', ?string $raw = null): void
    {
        $set('ia_message_type', $type);
        $set('ia_message', $message);
        $set('ia_pending_raw', $raw);

        $action->getLivewire()?->mountAction('updateIngredientsWithAi', context: [
            'schemaComponent' => 'form.ingredientsAiActions',
        ]);
    }
}
