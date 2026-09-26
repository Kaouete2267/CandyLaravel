<?php

namespace Modules\Ia\Support;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Allergenes\Models\Allergen;
use Modules\Allergenes\Support\AllergenTerms;
use Modules\Ia\Filament\Actions\ReviewIngredientsDiff;
use Modules\Ia\Filament\Actions\UpdateIngredientsWithAi;
use Modules\Support\Modules;

/**
 * Ajoute les allergènes détectés dans un texte d'ingrédients (français) à la sélection existante, sans
 * jamais en retirer (une case cochée manuellement pour une raison non déductible du texte, ex. contamination
 * croisée en atelier, doit survivre à un nouveau scan) : la liste « évolue » plutôt que d'être écrasée.
 * Utilisé aussi bien lors de la mise à jour initiale ({@see UpdateIngredientsWithAi})
 * que d'une relance après correction du scan brut ({@see ReviewIngredientsDiff}).
 */
class AllergenSync
{
    /** @return array<int, string> noms des allergènes nouvellement ajoutés, pour l'affichage dans la modale de comparaison */
    public static function apply(string $ingredientsFr, Set $set, Get $get): array
    {
        if (! Modules::enabled('Allergenes') || $ingredientsFr === '') {
            return [];
        }

        $detected = AllergenTerms::analyze($ingredientsFr);
        if ($detected['contains'] === [] && $detected['may_contain'] === []) {
            return [];
        }

        $ids = Allergen::whereIn('code', [...$detected['contains'], ...$detected['may_contain']])->pluck('id', 'code');

        $existingContains = collect($get('allergens_contains') ?? []);
        $existingMayContain = collect($get('allergens_may_contain') ?? []);

        $newContainsIds = collect($detected['contains'])->map(fn ($code) => $ids[$code] ?? null)->filter();
        $newMayContainIds = collect($detected['may_contain'])->map(fn ($code) => $ids[$code] ?? null)->filter();

        $addedIds = $newContainsIds->merge($newMayContainIds)
            ->reject(fn ($id) => $existingContains->contains($id) || $existingMayContain->contains($id))
            ->unique();

        $mergedContains = $existingContains->merge($newContainsIds)->unique()->values()->all();

        // "Contains" est prioritaire : un allergène qui y figure déjà ne redescend pas en "may_contain".
        $mergedMayContain = $existingMayContain->merge($newMayContainIds)
            ->unique()
            ->reject(fn ($id) => collect($mergedContains)->contains($id))
            ->values()
            ->all();

        $set('allergens_contains', $mergedContains);
        $set('allergens_may_contain', $mergedMayContain);

        // `pluck()` lit la colonne JSON brute sans passer par l'accesseur traduisible : il faut hydrater les
        // modèles via `get()` pour que `name` renvoie le libellé dans la langue courante.
        return Allergen::whereIn('id', $addedIds)->get()->pluck('name')->all();
    }
}
