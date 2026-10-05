<?php

namespace Modules\Ia\Services;

use Modules\Ia\Support\AiFeature;
use Modules\Ia\Support\AiFeatures;
use Modules\Ia\Support\AiPromptSection;
use Modules\Ia\Support\AllergensScan;

/**
 * Recopie telle quelle (OCR) la liste d'ingrédients visible sur une photo d'emballage, sans traduire ni
 * normaliser : le texte obtenu (« scan brut ») sert ensuite de point de départ à IngredientsAiWriter::rewrite().
 * Les mentions d'allergènes sont recopiées à part ({@see AllergensScan}), pour le contrôle humain des
 * allergènes cochés.
 */
class IngredientsScanner
{
    public const FEATURE = 'ingredients_scanner';

    public function __construct(private GeminiClient $gemini, private AiFeatures $features) {}

    /**
     * @param  array<int, array{mime: string, data: string}>  $images
     * @return array{raw: string, allergens: string, notes: ?string}
     */
    public function scan(array $images): array
    {
        if ($images === []) {
            throw new GeminiException('Envoyez d\'abord une photo de la liste d\'ingrédients.');
        }

        $feature = $this->features->get(self::FEATURE);
        $prompt = $feature->render('prompt', ['mentions_allergenes' => AllergensScan::PROMPT]);

        $result = $this->gemini->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'raw' => ['type' => 'STRING'],
                ...AllergensScan::schemaProperties(),
                'notes' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['raw', ...array_keys(AllergensScan::schemaProperties())],
        ], $images, temperature: $feature->temperature());

        return [
            'raw' => $result['raw'] ?? '',
            'allergens' => AllergensScan::compose($result),
            'notes' => $result['notes'] ?? null,
        ];
    }

    /**
     * Les consignes des mentions d'allergènes restent dans le code ({@see AllergensScan::PROMPT}) : elles
     * décrivent des champs du schéma de réponse, partagés avec {@see ProductDrafter}.
     */
    public static function aiFeature(): AiFeature
    {
        return new AiFeature(
            key: self::FEATURE,
            module: 'Ingrédients',
            label: 'Lecture de la liste d\'ingrédients sur photo',
            description: 'Recopie (OCR) la liste d\'ingrédients et les mentions d\'allergènes d\'une photo d\'emballage, sans traduire.',
            variables: [
                'mentions_allergenes' => 'consignes des champs de mentions d\'allergènes (fixes, liées au format de réponse)',
            ],
            sections: [
                new AiPromptSection('prompt', 'Prompt', <<<'PROMPT'
Tu es un outil d'OCR spécialisé dans les emballages alimentaires. Recopie EXACTEMENT le texte de la liste
d'ingrédients visible sur cette photo, dans sa langue d'origine, sans corriger l'orthographe, sans traduire et
sans réorganiser l'ordre. Si l'emballage affiche la liste en plusieurs langues, recopie uniquement celle en
français si elle est présente, sinon la première liste lisible. N'ajoute aucun commentaire ni mise en forme :
seulement le texte brut recopié, tel qu'imprimé, dans `raw`, SANS la mention d'allergènes qui la suit
éventuellement (elle est recopiée à part, voir plus bas). Si aucune liste d'ingrédients n'est lisible sur la
photo, renvoie une chaîne vide pour `raw` et explique pourquoi dans `notes`.

{{mentions_allergenes}}
PROMPT),
            ],
        );
    }
}
