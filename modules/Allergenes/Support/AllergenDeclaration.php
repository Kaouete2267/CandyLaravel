<?php

namespace Modules\Allergenes\Support;

use Modules\Allergenes\Models\Allergen;

/**
 * Génère le texte légal « ALLERGÈNES ALIMENTAIRES MAJEURS » à partir des allergènes déclarés dans le
 * module (au lieu d'un texte figé) : sert de contenu de départ pour le bloc d'information du panneau,
 * régénérable à la demande (bouton « Générer depuis le module Allergènes ») si la liste change.
 * Reste un texte libre une fois inséré : rien n'empêche l'utilisateur de le modifier ensuite.
 */
class AllergenDeclaration
{
    public static function html(string $locale = 'fr'): string
    {
        $names = Allergen::orderBy('position')->get()
            ->map(fn (Allergen $a) => '<b>'.e($a->getTranslation('name', $locale)).'</b>')
            ->all();

        if ($names === []) {
            return '';
        }

        return '<p>'.self::joinWithAnd($names).' et leurs produits dérivés font partie des allergènes à déclaration '
            .'obligatoire (règlement UE n° 1169/2011).</p>'
            .'<p><small>Tout produit contenant un allergène fait l\'objet d\'un étiquetage obligatoire. Doivent aussi '
            .'être précisés la base d\'une huile, les ajouts d\'eau ou de protéine et la présence involontaire '
            .'d\'allergènes majeurs.</small></p>';
    }

    /** @param  array<int, string>  $items */
    private static function joinWithAnd(array $items): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' et '.$last;
    }
}
