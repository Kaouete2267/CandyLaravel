<?php

namespace Modules\Ia\Services;

use Modules\Ia\Support\IngredientCategory;

/**
 * Réécrit une liste d'ingrédients « brute » (copiée d'un emballage, dans n'importe quelle langue) en une
 * liste compacte et normalisée, traduite dans chaque langue du site. Remplace l'ancien outil manuel
 * « Écriture assistée par formulaire » (4 étapes, cases à cocher) par un seul appel à l'IA.
 *
 * Le regroupement par catégorie s'est avéré peu fiable quand on demandait à l'IA de restructurer toute la
 * liste en un seul passage (elle réinventait parfois des catégories fonctionnelles comme « antioxydants »,
 * ou dupliquait un ingrédient déjà classé ailleurs sous un autre intitulé) : une tâche de « classer CET
 * ingrédient » est simple et fiable pour un LLM, une tâche de « restructurer tout l'ensemble en cohérence »
 * beaucoup moins. L'IA se limite donc à décomposer le texte en ingrédients atomiques et à leur assigner une
 * catégorie parmi une liste fermée (voir {@see IngredientCategory}) ; {@see IngredientGlossary} fait ensuite
 * autorité sur la catégorie/le texte final pour tout ingrédient déjà rencontré (cohérence entre produits, et
 * le glossaire se complète pour les nouveaux) ; le regroupement, le tri et le formatage final sont du PHP
 * déterministe, ci-dessous.
 */
class IngredientsWriter
{
    public function __construct(
        private GeminiClient $gemini,
        private IngredientGlossary $glossary,
    ) {}

    /**
     * @return array{ingredients: array<string, string>, notes: ?string}
     */
    public function rewrite(string $raw): array
    {
        if (blank($raw)) {
            throw new GeminiException('Collez ou saisissez d\'abord une liste d\'ingrédients.');
        }

        $locales = array_keys(config('bonbon.locales'));
        $categories = IngredientCategory::values();

        $prompt = $this->prompt($raw, $locales, $categories);

        $itemProperties = array_fill_keys($locales, ['type' => 'STRING']);
        $itemProperties['category'] = ['type' => 'STRING', 'enum' => $categories, 'nullable' => true];

        $result = $this->gemini->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => $itemProperties,
                        'required' => [...$locales, 'category'],
                    ],
                ],
                'notes' => ['type' => 'STRING', 'nullable' => true],
            ],
            'required' => ['items'],
        ]);

        $items = array_map(
            fn (array $item) => $this->glossary->resolve($item, $locales),
            $result['items'] ?? []
        );

        return [
            'ingredients' => $this->assemble($items, $locales),
            'notes' => $result['notes'] ?? null,
        ];
    }

    /** @param  array<int, string>  $locales @param  array<int, string>  $categories */
    private function prompt(string $raw, array $locales, array $categories): string
    {
        $localeList = implode(', ', $locales);
        $categoryList = implode(', ', $categories);

        return <<<PROMPT
Tu es l'assistant de catalogue d'une confiserie qui vend des bonbons en vrac. Voici une liste d'ingrédients
telle qu'elle apparaît sur l'emballage d'origine (langue libre, ponctuation parfois désordonnée) :

« {$raw} »

Décompose-la en ingrédients ATOMIQUES (un additif ou une substance = un élément de la liste `items`), SANS
les regrouper ni les mettre en forme : un programme s'en charge ensuite. Pour chaque élément :
- `category` : la catégorie CHIMIQUE qui correspond le mieux, choisie STRICTEMENT dans cette liste fermée :
  {$categoryList}. Mets `null` si l'ingrédient est une substance isolée qui n'appartient à aucune de ces
  catégories (ex. sucre, farine de blé, dextrose, huile de palme, gélatine). N'utilise JAMAIS une catégorie
  FONCTIONNELLE absente de cette liste (antioxydant, conservateur, émulsifiant, épaississant…) même si
  l'emballage d'origine range l'ingrédient sous cet intitulé : classe-le par sa nature chimique. Exemple :
  l'acide ascorbique va dans "acides" même si l'emballage le présente sous "antioxydants".
- `{$localeList}` : le nom de l'ingrédient SEUL, traduit fidèlement dans chacune de ces langues, SANS
  répéter le mot de catégorie choisi (pour la catégorie "acides", écris "citrique", jamais "acide citrique").
  Garde la préposition naturelle quand il y en a une (« de palme », « de glucose-fructose »), toujours de la
  même façon d'un ingrédient à l'autre. Pour un colorant, le même code réglementaire E dans les trois langues
  (ex. "E133"). Si `category` est `null`, écris le nom complet de l'ingrédient tel quel, traduit.

Règles générales :
1. N'invente jamais un ingrédient absent du texte source. Si le texte source est illisible ou trop ambigu
   pour être décomposé fidèlement, explique-le dans `notes` plutôt que de deviner.
2. N'ajoute AUCUN élément pour une mention d'allergène ou une phrase d'avertissement (ex. « peut contenir
   des traces de… ») : les allergènes sont gérés séparément ailleurs sur la fiche produit.
3. Si le même ingrédient apparaît plusieurs fois dans le texte source, même sous des intitulés ou catégories
   différents, ne le liste qu'une seule fois dans `items`.
PROMPT;
    }

    /**
     * Regroupe par catégorie (une seule fois par catégorie, à la position de sa première apparition — les
     * ingrédients isolés gardent l'ordre du texte source, qui reflète l'ordre décroissant de proportion),
     * trie chaque groupe alphabétiquement, puis renvoie les arômes en avant-dernière position et les
     * colorants en dernière — voir « Aides : normes de présentation » sur le formulaire produit.
     *
     * @param  array<int, array{category: ?string, fr: string, nl: string, en: string}>  $items
     * @param  array<int, string>  $locales
     * @return array<string, string>
     */
    private function assemble(array $items, array $locales): array
    {
        $slots = [];
        $slotIndexByCategory = [];
        $seen = [];

        foreach ($items as $item) {
            $dedupeKey = ($item['category'] ?? '').'|'.IngredientGlossary::normalize($item['fr'] ?? '');
            if ($dedupeKey === '|' || isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $category = $item['category'] ?? null;

            if ($category === null) {
                $slots[] = ['type' => 'single', 'item' => $item];

                continue;
            }

            if (! isset($slotIndexByCategory[$category])) {
                $slotIndexByCategory[$category] = count($slots);
                $slots[] = ['type' => 'group', 'category' => $category, 'items' => []];
            }

            $slots[$slotIndexByCategory[$category]]['items'][] = $item;
        }

        // Les arômes en avant-dernière position, les colorants en dernière : on les retire puis on les
        // rajoute dans cet ordre, s'ils existent.
        $aromes = IngredientCategory::Aromes->value;
        $colorants = IngredientCategory::Colorants->value;
        $trailing = [];
        foreach ([$aromes, $colorants] as $category) {
            if (isset($slotIndexByCategory[$category])) {
                $trailing[] = $slots[$slotIndexByCategory[$category]];
            }
        }
        $slots = [
            ...array_values(array_filter($slots, fn (array $slot) => ($slot['category'] ?? null) !== $aromes && ($slot['category'] ?? null) !== $colorants)),
            ...$trailing,
        ];

        $ingredients = [];
        foreach ($locales as $locale) {
            $ingredients[$locale] = implode(', ', array_map(
                fn (array $slot) => $this->renderSlot($slot, $locale),
                $slots
            ));
        }

        return $ingredients;
    }

    /** @param  array{type: string, item?: array, category?: string, items?: array}  $slot */
    private function renderSlot(array $slot, string $locale): string
    {
        if ($slot['type'] === 'single') {
            return $slot['item'][$locale];
        }

        $category = IngredientCategory::from($slot['category']);
        $texts = array_map(fn (array $item) => $item[$locale], $slot['items']);
        usort($texts, fn (string $a, string $b) => IngredientGlossary::normalize($a) <=> IngredientGlossary::normalize($b));

        if (count($texts) === 1) {
            return $category === IngredientCategory::Colorants
                ? $category->label($locale, plural: false).' : '.$texts[0]
                : $category->label($locale, plural: false).' '.$texts[0];
        }

        return $category->label($locale, plural: true).' ('.implode(', ', $texts).')';
    }
}
