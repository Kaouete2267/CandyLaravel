<?php

namespace Modules\Ia\Services;

use Illuminate\Support\Str;
use Modules\Ia\Support\AiFeature;
use Modules\Ia\Support\AiFeatures;
use Modules\Ia\Support\AiPromptSection;

/** Crée la fiche d'un allergène (code + traductions) à partir d'un nom saisi dans n'importe quelle langue. */
class AllergenDrafter
{
    public const FEATURE = 'allergen_drafter';

    public function __construct(private GeminiClient $gemini, private AiFeatures $features) {}

    /**
     * @return array{code: string, name: array<string,string>}
     */
    public function draft(string $name): array
    {
        $locales = array_keys(config('bonbon.locales'));

        $feature = $this->features->get(self::FEATURE);
        $prompt = $feature->render('prompt', ['nom' => $name, 'langues' => implode(', ', $locales)]);

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'code' => ['type' => 'STRING'],
                'name' => [
                    'type' => 'OBJECT',
                    'properties' => array_fill_keys($locales, ['type' => 'STRING']),
                    'required' => $locales,
                ],
            ],
            'required' => ['code', 'name'],
        ];

        $result = $this->gemini->generateJson($prompt, $schema, temperature: $feature->temperature());

        return [
            'code' => Str::of($result['code'] ?? $name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(),
            'name' => array_map('trim', $result['name'] ?? []),
        ];
    }

    public static function aiFeature(): AiFeature
    {
        return new AiFeature(
            key: self::FEATURE,
            module: 'Allergènes',
            label: 'Création d\'allergène',
            description: 'Traduit le nom d\'un nouvel allergène et lui donne un code technique.',
            variables: [
                'nom' => 'le nom saisi par l\'utilisateur',
                'langues' => 'les codes des langues du site (ex. fr, nl, en)',
            ],
            sections: [
                new AiPromptSection('prompt', 'Prompt', <<<'PROMPT'
Un allergène alimentaire doit être ajouté au catalogue d'une confiserie : « {{nom}} ».
Donne son nom usuel tel qu'il apparaît sur les étiquettes de denrées alimentaires, dans chacune de ces langues : {{langues}}.
Le nom est court, au singulier ou tel qu'on le lit dans la liste officielle des allergènes de l'UE quand il en fait partie (ex. « Fruits à coque », « Lait »).
Donne aussi `code` : identifiant technique en anglais, en minuscules, avec des underscores (ex. « tree_nuts », « sulphites »).
PROMPT),
            ],
        );
    }
}
