<?php

namespace Modules\Allergenes\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Allergenes\Enums\AllergenLevel;

/** Opérations sur les allergènes d'un produit Lunar (la relation `allergens` est ajoutée par le module). */
class ProductAllergens
{
    /**
     * Remplace les allergènes du produit. Un allergène présent dans les deux listes compte comme "contient".
     *
     * @param  array<int, int|string>  $contains
     * @param  array<int, int|string>  $mayContain
     */
    public static function sync(Model $product, array $contains, array $mayContain = []): void
    {
        $contains = array_map('intval', $contains);
        $mayContain = array_diff(array_map('intval', $mayContain), $contains);

        $sync = [];
        foreach ($contains as $id) {
            $sync[$id] = ['type' => AllergenLevel::Contains->value];
        }
        foreach ($mayContain as $id) {
            $sync[$id] = ['type' => AllergenLevel::MayContain->value];
        }

        $product->allergens()->sync($sync);
    }

    /** @return array<int, int> ids des allergènes du produit pour un niveau donné */
    public static function ids(Model $product, AllergenLevel $level): array
    {
        return $product->allergens
            ->filter(fn ($allergen) => $allergen->pivot->type === $level->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Restreint la requête aux produits exempts des allergènes donnés.
     * Par défaut les traces ("peut contenir") comptent aussi comme exclusion.
     *
     * @param  array<int, int|string>  $allergenIds
     */
    public static function excluding(Builder $query, array $allergenIds, bool $includeTraces = true): Builder
    {
        if ($allergenIds === []) {
            return $query;
        }

        return $query->whereDoesntHave('allergens', function (Builder $q) use ($allergenIds, $includeTraces) {
            $q->whereIn('allergens.id', $allergenIds);

            if (! $includeTraces) {
                $q->where('allergen_product.type', AllergenLevel::Contains->value);
            }
        });
    }
}
