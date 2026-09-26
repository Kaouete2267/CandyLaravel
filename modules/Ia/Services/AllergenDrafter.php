<?php

namespace Modules\Ia\Services;

use Illuminate\Support\Str;

/** Crée la fiche d'un allergène (code + traductions) à partir d'un nom saisi dans n'importe quelle langue. */
class AllergenDrafter
{
    public function __construct(private GeminiClient $gemini) {}

    /**
     * @return array{code: string, name: array<string,string>}
     */
    public function draft(string $name): array
    {
        $locales = array_keys(config('bonbon.locales'));
        $localeList = implode(', ', $locales);

        $prompt = <<<PROMPT
Un allergène alimentaire doit être ajouté au catalogue d'une confiserie : « {$name} ».
Donne son nom usuel tel qu'il apparaît sur les étiquettes de denrées alimentaires, dans chacune de ces langues : {$localeList}.
Le nom est court, au singulier ou tel qu'on le lit dans la liste officielle des allergènes de l'UE quand il en fait partie (ex. « Fruits à coque », « Lait »).
Donne aussi `code` : identifiant technique en anglais, en minuscules, avec des underscores (ex. « tree_nuts », « sulphites »).
PROMPT;

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

        $result = $this->gemini->generateJson($prompt, $schema);

        return [
            'code' => Str::of($result['code'] ?? $name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(),
            'name' => array_map('trim', $result['name'] ?? []),
        ];
    }
}
