<?php

namespace Modules\Legacy\Services;

use Modules\Allergenes\Support\AllergenTerms;

/**
 * Déduit les allergènes d'une liste d'ingrédients en français, comme le faisait l'ancienne application
 * (mots-clés + mention « Peut contenir des traces de… »). La logique vit dans le module Allergènes
 * (AllergenTerms), partagée avec le surlignage du panneau imprimé.
 */
class AllergenDetector
{
    /**
     * @return array{contains: array<int,string>, may_contain: array<int,string>, notes: array<int,string>}
     */
    public function detect(string $ingredients): array
    {
        return AllergenTerms::analyze($ingredients);
    }
}
