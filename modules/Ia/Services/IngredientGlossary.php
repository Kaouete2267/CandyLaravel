<?php

namespace Modules\Ia\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Ia\Models\IngredientTerm;

/**
 * Mémorise, ingrédient par ingrédient, la catégorie et le texte final déjà retenus, pour que
 * {@see IngredientsWriter} classe de façon cohérente et déterministe d'un produit à l'autre plutôt que de
 * faire confiance à l'IA pour réinventer le classement à chaque appel. Un ingrédient jamais rencontré est
 * ajouté au passage (non relu) : le glossaire se complète tout seul au fil des scans.
 */
class IngredientGlossary
{
    private ?Collection $terms = null;

    /**
     * @param  array{category: ?string, fr: string, nl: string, en: string}  $item  tel que renvoyé par l'IA
     * @param  array<int, string>  $locales
     * @return array{category: ?string, fr: string, nl: string, en: string} la version à utiliser (celle du
     *                                                                      glossaire si l'ingrédient est déjà connu, sinon celle de l'IA)
     */
    public function resolve(array $item, array $locales): array
    {
        $key = self::normalize($item['fr'] ?? '');

        if ($key === '') {
            return $item;
        }

        if ($term = $this->findByAlias($key)) {
            return [
                'category' => $term->category,
                ...collect($locales)->mapWithKeys(fn (string $locale) => [
                    $locale => $term->getTranslation('name', $locale) ?: ($item[$locale] ?? $item['fr']),
                ])->all(),
            ];
        }

        $term = IngredientTerm::create([
            'category' => $item['category'] ?? null,
            'aliases' => [$key],
            'name' => collect($locales)->mapWithKeys(fn (string $locale) => [$locale => $item[$locale] ?? $item['fr']])->all(),
            'reviewed_at' => null,
        ]);

        $this->terms?->push($term);

        return $item;
    }

    private function findByAlias(string $key): ?IngredientTerm
    {
        return $this->allTerms()->first(fn (IngredientTerm $term) => in_array($key, $term->aliases ?? [], true));
    }

    private function allTerms(): Collection
    {
        return $this->terms ??= IngredientTerm::all();
    }

    /**
     * Sans casse ni accents, pour que « Acide Citrique » et « acide citrique » désignent la même entrée.
     * Sans préposition de tête (« de »/« d' ») non plus : l'IA n'est pas constante sur ce point d'un appel à
     * l'autre (« de palme » une fois, « palme » une autre) pour un même ingrédient réel — les considérer
     * comme deux entrées différentes les ferait apparaître deux fois dans la liste finale.
     */
    public static function normalize(string $text): string
    {
        $normalized = Str::of($text)->ascii()->lower()->trim()->toString();

        return preg_replace('~^(?:de |d\')~', '', $normalized) ?? $normalized;
    }
}
