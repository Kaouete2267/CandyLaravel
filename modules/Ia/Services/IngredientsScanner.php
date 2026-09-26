<?php

namespace Modules\Ia\Services;

/**
 * Recopie telle quelle (OCR) la liste d'ingrédients visible sur une photo d'emballage, sans traduire ni
 * normaliser : le texte obtenu (« scan brut ») sert ensuite de point de départ à IngredientsWriter::rewrite().
 */
class IngredientsScanner
{
    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  array<int, array{mime: string, data: string}>  $images
     * @return array{raw: string, notes: ?string}
     */
    public function scan(array $images): array
    {
        if ($images === []) {
            throw new GeminiException('Envoyez d\'abord une photo de la liste d\'ingrédients.');
        }

        $prompt = <<<'PROMPT'
Tu es un outil d'OCR spécialisé dans les emballages alimentaires. Recopie EXACTEMENT le texte de la liste
d'ingrédients visible sur cette photo, dans sa langue d'origine, sans corriger l'orthographe, sans traduire et
sans réorganiser l'ordre. Si l'emballage affiche la liste en plusieurs langues, recopie uniquement celle en
français si elle est présente, sinon la première liste lisible. N'ajoute aucun commentaire ni mise en forme :
seulement le texte brut recopié, tel qu'imprimé. Si aucune liste d'ingrédients n'est lisible sur la photo,
renvoie une chaîne vide pour `raw` et explique pourquoi dans `notes`.
PROMPT;

        $result = $this->gemini->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'raw' => ['type' => 'STRING'],
                'notes' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['raw'],
        ], $images);

        return [
            'raw' => $result['raw'] ?? '',
            'notes' => $result['notes'] ?? null,
        ];
    }
}
