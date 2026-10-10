<?php

namespace Modules\Ia\Services;

use Modules\Allergenes\Models\Allergen;
use Modules\Ia\Support\AiFeature;
use Modules\Ia\Support\AiFeatures;
use Modules\Ia\Support\AiPromptSection;
use Modules\Ia\Support\AllergensScan;
use Modules\Support\Modules;

/**
 * Propose une fiche produit (traductions, ingrédients, allergènes) à partir d'un nom et/ou de photos
 * (face avant, liste d'ingrédients). Le résultat est un brouillon que l'humain relit avant enregistrement.
 */
class ProductDrafter
{
    public const FEATURE = 'product_drafter';

    public function __construct(private GeminiClient $gemini, private AiFeatures $features) {}

    /**
     * La règle des allergènes (liste fermée des codes, construite depuis la base) et les consignes des mentions
     * d'allergènes ({@see AllergensScan::PROMPT}) restent dans le code : elles décrivent le schéma de réponse.
     */
    public static function aiFeature(): AiFeature
    {
        return new AiFeature(
            key: self::FEATURE,
            module: 'Catalogue',
            label: 'Création de fiche produit',
            description: 'Propose une fiche produit (traductions, ingrédients, allergènes) à partir d\'un nom et/ou de photos.',
            variables: [
                'indication' => 'le nom ou l\'indication saisi par l\'utilisateur',
                'langues' => 'les codes des langues du site (ex. fr, nl, en)',
                'regle_allergenes' => 'la règle du champ `allergens` avec la liste des codes existants (vide sans module Allergènes)',
                'mentions_allergenes' => 'consignes des champs de mentions d\'allergènes (fixes, liées au format de réponse)',
            ],
            sections: [
                new AiPromptSection('plan', 'Plan', <<<'PROMPT'
{{role}}

Indication de l'utilisateur : {{indication}}
Des photos du produit ou de son emballage sont jointes le cas échéant (lis la marque, le nom, le poids, le code-barres et surtout la liste d'ingrédients et les mentions d'allergènes).

Règles :
{{regles}}

{{mentions_allergenes}}
PROMPT, 'Assemble les autres sections : retirer une variable retire la section du prompt.'),
                new AiPromptSection('role', 'Rôle', <<<'PROMPT'
Tu es l'assistant de catalogue d'une confiserie qui vend des bonbons en vrac (au poids, au kg).
Crée une fiche produit à partir des informations ci-dessous.
PROMPT),
                new AiPromptSection('regles', 'Règles', <<<'PROMPT'
- Rédige `name`, `description` et `ingredients` dans chacune de ces langues : {{langues}}.
- `name` : nom commercial court, sans la marque. `description` : 1 à 2 phrases factuelles et appétissantes (goût, texture, forme), sans slogan ni superlatif inventé.
- `ingredients` : recopie/traduis la liste d'ingrédients si elle est lisible, sinon chaîne vide. N'invente jamais d'ingrédients.{{regle_allergenes}}
- `ingredients_scan` : la liste d'ingrédients recopiée EXACTEMENT telle qu'imprimée sur la photo, dans sa langue
  d'origine, sans corriger, traduire ni réorganiser, SANS la mention d'allergènes qui la suit éventuellement. Si
  l'emballage l'affiche en plusieurs langues, uniquement celle en français si elle est présente, sinon la première
  lisible. Chaîne vide sans photo lisible.
- `barcode` : seulement s'il est lisible sur les photos, sinon null.
- `brand` : marque du fabricant, ou null.
- `notes` : signale en français ce qui est incertain ou illisible (à vérifier par un humain), sinon null.
PROMPT, 'Les noms entre accents graves (`name`, `ingredients`…) sont les champs de la réponse : ne les renommez pas.'),
            ],
        );
    }

    /**
     * `ingredients_scan` et `allergens_scan` : textes lus tels quels sur la photo, gardés sur la fiche pour le
     * contrôle humain (mêmes champs que ceux remplis par {@see IngredientsScanner}).
     *
     * @param  array<int, array{mime: string, data: string}>  $images
     * @return array{brand: ?string, name: array<string,string>, description: array<string,string>, ingredients: array<string,string>, ingredients_scan: string, allergens_scan: string, barcode: ?string, allergens: array<int, array{code: string, level: string}>, notes: ?string}
     */
    public function draft(?string $hint, array $images = []): array
    {
        if (blank($hint) && $images === []) {
            throw new GeminiException('Indiquez un nom de produit ou envoyez au moins une photo.');
        }

        $locales = array_keys(config('bonbon.locales'));
        $allergens = Modules::enabled('Allergenes') ? Allergen::orderBy('position')->get() : collect();
        $codes = $allergens->pluck('code')->all();

        $localeList = implode(', ', $locales);
        $hintText = filled($hint) ? '« '.trim($hint).' »' : '(aucune, se baser sur les photos)';

        $allergenRule = $codes === [] ? '' : "\n- `allergens` : uniquement des codes de cette liste fermée :\n"
            .$allergens->map(fn (Allergen $a) => "  - {$a->code} : {$a->getTranslation('name', 'fr')}")->implode("\n")
            ."\n  Utilise le niveau \"contains\" si l'allergène figure dans les ingrédients, \"may_contain\" pour une mention « peut contenir des traces de… ». En l'absence d'information fiable, ne mets rien plutôt que de deviner, et explique-le dans `notes`.";

        $feature = $this->features->get(self::FEATURE);
        $prompt = $feature->render('plan', [
            'indication' => $hintText,
            'langues' => $localeList,
            'regle_allergenes' => $allergenRule,
            'mentions_allergenes' => AllergensScan::PROMPT,
        ]);

        $localized = [
            'type' => 'OBJECT',
            'properties' => array_fill_keys($locales, ['type' => 'STRING']),
            'required' => $locales,
        ];

        $properties = [
            'brand' => ['type' => 'STRING', 'nullable' => true],
            'name' => $localized,
            'description' => $localized,
            'ingredients' => $localized,
            'ingredients_scan' => ['type' => 'STRING'],
            ...AllergensScan::schemaProperties(),
            'barcode' => ['type' => 'STRING', 'nullable' => true],
            'notes' => ['type' => 'STRING', 'nullable' => true],
        ];

        if ($codes !== []) {
            $properties['allergens'] = [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'code' => ['type' => 'STRING', 'enum' => $codes],
                        'level' => ['type' => 'STRING', 'enum' => ['contains', 'may_contain']],
                    ],
                    'required' => ['code', 'level'],
                ],
            ];
        }

        $result = $this->gemini->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => $properties,
            'required' => ['name', 'description', 'ingredients'],
        ], $images, temperature: $feature->temperature());

        // Ne garde que des codes existants, une seule fois (le niveau le plus strict l'emporte).
        $clean = [];
        foreach ($result['allergens'] ?? [] as $row) {
            $code = $row['code'] ?? null;
            if (! in_array($code, $codes, true)) {
                continue;
            }
            if (($clean[$code]['level'] ?? null) !== 'contains') {
                $clean[$code] = ['code' => $code, 'level' => ($row['level'] ?? null) === 'contains' ? 'contains' : 'may_contain'];
            }
        }
        $result['allergens'] = array_values($clean);
        $result['ingredients_scan'] = trim($result['ingredients_scan'] ?? '');
        $result['allergens_scan'] = AllergensScan::compose($result);

        return $result;
    }
}
