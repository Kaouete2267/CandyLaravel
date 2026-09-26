<?php

namespace Modules\Ia\Services;

use Modules\Allergenes\Models\Allergen;
use Modules\Support\Modules;

/**
 * Propose une fiche produit (traductions, ingrédients, allergènes) à partir d'un nom et/ou de photos
 * (face avant, liste d'ingrédients). Le résultat est un brouillon que l'humain relit avant enregistrement.
 */
class ProductDrafter
{
    public function __construct(private GeminiClient $gemini) {}

    /**
     * @param  array<int, array{mime: string, data: string}>  $images
     * @return array{brand: ?string, name: array<string,string>, description: array<string,string>, ingredients: array<string,string>, bag_weight_kg: ?float, barcode: ?string, allergens: array<int, array{code: string, level: string}>, notes: ?string}
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

        $prompt = <<<PROMPT
Tu es l'assistant de catalogue d'une confiserie qui vend des bonbons en vrac (au poids, en sacs).
Crée une fiche produit à partir des informations ci-dessous.

Indication de l'utilisateur : {$hintText}
Des photos du produit ou de son emballage sont jointes le cas échéant (lis la marque, le nom, le poids, le code-barres et surtout la liste d'ingrédients et les mentions d'allergènes).

Règles :
- Rédige `name`, `description` et `ingredients` dans chacune de ces langues : {$localeList}.
- `name` : nom commercial court, sans la marque. `description` : 1 à 2 phrases factuelles et appétissantes (goût, texture, forme), sans slogan ni superlatif inventé.
- `ingredients` : recopie/traduis la liste d'ingrédients si elle est lisible, sinon chaîne vide. N'invente jamais d'ingrédients.{$allergenRule}
- `bag_weight_kg` et `barcode` : seulement s'ils sont lisibles sur les photos, sinon null.
- `brand` : marque du fabricant, ou null.
- `notes` : signale en français ce qui est incertain ou illisible (à vérifier par un humain), sinon null.
PROMPT;

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
            'bag_weight_kg' => ['type' => 'NUMBER', 'nullable' => true],
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
        ], $images);

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

        return $result;
    }
}
