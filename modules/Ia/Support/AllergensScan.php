<?php

namespace Modules\Ia\Support;

use Modules\Ia\Services\IngredientsScanner;
use Modules\Ia\Services\ProductDrafter;

/**
 * « Mentions allergènes (scan) » : ce que l'emballage dit des allergènes, lu tel quel sur la photo, pour que
 * l'humain puisse justifier chaque case « Contient » / « Peut contenir » cochée. Deux choses y figurent :
 * la mention de traces (« Peut contenir… ») recopiée mot pour mot, et les allergènes que l'emballage met
 * visuellement en évidence (gras, majuscules, souligné), dans la liste d'ingrédients comme dans la mention.
 *
 * Partagé par les deux outils IA qui lisent une photo ({@see IngredientsScanner}, {@see ProductDrafter}) pour
 * qu'ils extraient et présentent ce texte exactement de la même façon.
 */
class AllergensScan
{
    /** Consignes à ajouter au prompt, pour les champs de {@see schemaProperties()}. */
    public const PROMPT = <<<'PROMPT'
Mentions d'allergènes, dans la même langue que la liste d'ingrédients recopiée :
- `allergen_mention` : la mention de traces ou d'allergènes (« Peut contenir… », « Fabriqué dans un atelier
  qui utilise… », « Contient… ») recopiée EXACTEMENT, telle qu'imprimée, sans la traduire. Chaîne vide si
  l'emballage n'en porte pas.
- `highlighted_allergens` : chaque mot ou groupe de mots que l'emballage met visuellement en évidence (gras,
  majuscules, souligné, autre couleur), dans la liste d'ingrédients comme dans la mention, recopié tel
  qu'imprimé, dans l'ordre d'apparition. Liste vide si rien n'est mis en évidence.
PROMPT;

    /** @return array<string, array<string, mixed>> */
    public static function schemaProperties(): array
    {
        return [
            'allergen_mention' => ['type' => 'STRING'],
            'highlighted_allergens' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        ];
    }

    /**
     * Texte enregistré dans l'attribut `allergens_scan` : la mention telle quelle, puis la liste des mots mis
     * en évidence sur une ligne à part.
     *
     * @param  array{allergen_mention?: ?string, highlighted_allergens?: array<int, string>}  $result
     */
    public static function compose(array $result): string
    {
        $mention = trim($result['allergen_mention'] ?? '');
        $highlighted = array_values(array_filter(array_map('trim', $result['highlighted_allergens'] ?? []), 'filled'));

        return implode("\n", array_filter([
            $mention,
            $highlighted === [] ? '' : 'Mis en évidence sur l\'emballage : '.implode(', ', $highlighted),
        ], 'filled'));
    }
}
