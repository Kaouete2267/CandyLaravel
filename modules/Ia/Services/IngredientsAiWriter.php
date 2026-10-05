<?php

namespace Modules\Ia\Services;

use Modules\Ia\Support\AiFeature;
use Modules\Ia\Support\AiFeatures;
use Modules\Ia\Support\AiPromptSection;

/**
 * Réécriture d'une liste d'ingrédients entièrement confiée à l'IA : décomposition, traduction,
 * regroupement par thématique, tri et mise en forme finale, dans un cadre précisé par le prompt (normes de
 * présentation du formulaire produit). Pas d'assemblage PHP : le résultat de l'IA est repris
 * tel quel, et l'IA liste dans `notes` les conversions de noms et les choix importants qu'elle a faits, pour
 * que l'utilisateur puisse les vérifier dans la modale de comparaison.
 */
class IngredientsAiWriter
{
    public const FEATURE = 'ingredients_writer';

    public function __construct(private GeminiClient $gemini, private AiFeatures $features) {}

    /**
     * `notes` est une liste à puces HTML construite ici à partir des notes typées de l'IA (texte échappé).
     * `debug` expose les instructions système, le message envoyé et la réponse brute de l'IA, pour le mode
     * debug de l'interface. `source_fr` est la traduction française brute du texte transmis (vide si l'IA ne
     * l'a pas fournie) : la base de comparaison du résultat français. Prompt et température : voir
     * {@see AiFeature()} (température 0 par défaut : respect strict des règles, pas de créativité).
     *
     * @return array{ingredients: array<string, string>, source_fr: string, notes: ?string, debug: array{prompt: string, response: array<string, mixed>}}
     */
    public function rewrite(string $raw): array
    {
        if (blank($raw)) {
            throw new GeminiException('Collez ou saisissez d\'abord une liste d\'ingrédients.');
        }

        $locales = array_keys(config('bonbon.locales'));

        $feature = $this->features->get(self::FEATURE);
        $variables = [
            'texte_source' => $raw,
            'langues' => implode(', ', $locales),
            'types_notes' => implode(', ', array_map(fn (string $type) => "« {$type} »", self::NOTE_TYPES)),
        ];

        $systemInstruction = $feature->render('plan', $variables);
        $prompt = $feature->render('message', $variables);

        $result = $this->gemini->generateJson($prompt, [
            'type' => 'OBJECT',
            'properties' => [
                'ingredients' => [
                    'type' => 'OBJECT',
                    'properties' => array_fill_keys($locales, ['type' => 'STRING']),
                    'required' => $locales,
                ],
                'sourceFr' => ['type' => 'STRING'],
                'notes' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'type' => ['type' => 'STRING', 'enum' => self::NOTE_TYPES],
                            'source' => ['type' => 'STRING'],
                            'result' => ['type' => 'STRING'],
                            'detail' => ['type' => 'STRING'],
                        ],
                        'required' => ['type', 'source', 'result', 'detail'],
                        // Sans ordre explicite, Gemini génère les propriétés par ordre alphabétique : `detail`
                        // serait rédigé avant que `source` et `result` soient posés côte à côte.
                        'propertyOrdering' => ['type', 'source', 'result', 'detail'],
                    ],
                ],
            ],
            'required' => ['sourceFr', 'ingredients', 'notes'],
            'propertyOrdering' => ['sourceFr', 'ingredients', 'notes'],
        ], temperature: $feature->temperature(), systemInstruction: $systemInstruction);

        $ingredients = [];
        foreach ($locales as $locale) {
            $ingredients[$locale] = trim($result['ingredients'][$locale] ?? '');
        }

        return [
            'ingredients' => $ingredients,
            'source_fr' => self::plainText($result['sourceFr'] ?? ''),
            'notes' => $this->renderNotes($result['notes'] ?? [], $this->characterDelta($result['sourceFr'] ?? '', $ingredients['fr'] ?? '')),
            'debug' => [
                'prompt' => "=== Instructions système ===\n{$systemInstruction}\n\n=== Message ===\n{$prompt}",
                'response' => $result,
            ],
        ];
    }

    /**
     * Types de notes autorisés, qui servent aussi de libellé affiché. Les notes sont typées (liste fermée) plutôt qu'en
     * texte libre : en texte libre, l'IA décrivait systématiquement l'application des normes de présentation
     * (« les arômes ont été replacés en avant-dernière position »…) malgré les consignes contraires ; sans type
     * pour ce genre de remarque, elle n'a plus de case où la ranger.
     */
    private const NOTE_TYPES = [
        'Nom modifié',
        'Code E',
        'Doublon fusionné',
        'Regroupement écarté',
        'Traduction incertaine',
        'Texte illisible ou ambigu',
    ];

    /**
     * L'IA écrit parfois ses textes avec des entités HTML (« &eacute; ») : décodées ici, sinon l'échappement
     * au rendu les afficherait telles quelles.
     */
    private static function plainText(string $text): string
    {
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Écart de longueur entre le texte final français et la traduction française non normalisée du source
     * (`sourceFr`, fournie par l'IA). Compté ici plutôt que par l'IA : les modèles de langage comptent mal les
     * caractères (+11 annoncé pour un écart réel de -25). Négatif quand le résultat est plus court.
     */
    private function characterDelta(string $sourceFr, string $resultFr): ?int
    {
        $sourceFr = self::plainText($sourceFr);

        if ($sourceFr === '' || $resultFr === '') {
            return null;
        }

        return mb_strlen($resultFr) - mb_strlen($sourceFr);
    }

    /**
     * Le nombre de caractères gagnés/perdus est affiché en tête, au-dessus des notes, mais n'en est pas une :
     * quand il en était une, la consigne « chaque note décrit un écart entre `source` et `result` » poussait
     * l'IA à le remplir de mots source/résultat et d'explications.
     *
     * @param  array<int, array{type?: string, source?: string, result?: string, detail?: string}>  $notes
     */
    private function renderNotes(array $notes, ?int $characterDelta): ?string
    {
        $items = [];
        foreach ($notes as $note) {
            $label = in_array($note['type'] ?? null, self::NOTE_TYPES, true) ? $note['type'] : null;
            $source = self::plainText($note['source'] ?? '');
            $detail = self::plainText($note['detail'] ?? '');

            if ($label === null || ($source === '' && $detail === '')) {
                continue;
            }

            $result = self::plainText($note['result'] ?? '');
            $change = implode(' → ', array_map(fn (string $text) => '« '.e($text).' »', array_filter([$source, $result], 'filled')));

            $items[] = '<li><strong>'.e($label).'</strong> : '
                .$change.($change !== '' && $detail !== '' ? ' — ' : '')
                .e($detail).'</li>';
        }

        $html = ($characterDelta !== null
                ? '<p><strong>Différence de caractères</strong> : '.($characterDelta > 0 ? '+' : '').$characterDelta.'</p>'
                : '')
            .($items === [] ? '' : '<ul>'.implode('', $items).'</ul>');

        return $html === '' ? null : $html;
    }

    /**
     * Sections du prompt réglables dans Réglages IA. Le message utilisateur (`message`) ne contient que la donnée
     * à traiter ; les règles vont dans les instructions système (`plan`, qui insère les autres sections), que
     * Gemini traite comme un cadre absolu plutôt que comme une demande.
     *
     * Les remplacements imposés s'appliquent à la traduction française (`sourceFr`, laissée intacte pour la
     * comparaison) avant la normalisation : ils apparaissent donc comme des changements dans le diff.
     */
    public static function aiFeature(): AiFeature
    {
        return new AiFeature(
            key: self::FEATURE,
            module: 'Ingrédients',
            label: 'Réécriture des ingrédients',
            description: 'Traduit et normalise une liste d\'ingrédients (photo lue ou texte saisi) dans chaque langue du site.',
            variables: [
                'texte_source' => 'la liste d\'ingrédients à traiter, telle que transmise',
                'langues' => 'les codes des langues du site (ex. fr, nl, en)',
                'types_notes' => 'la liste fermée des types de notes',
            ],
            temperature: 0.0,
            sections: [
                new AiPromptSection('plan', 'Plan (instructions système)', <<<'PROMPT'
{{role}}

Règles générales :
{{regles_generales}}

Remplacements imposés (terme du texte source ou de sa traduction française → texte à utiliser à la place) :
{{remplacements}}

Normes de présentation :
{{normes_presentation}}

Consigne du champ sourceFr :

{{consigne_source_fr}}

Consignes du champ notes :

{{consignes_notes}}

Traitement à réaliser dans cet ordre :
{{etapes}}
PROMPT, 'Assemble les autres sections dans l\'ordre voulu : retirer une variable retire la section du prompt.'),
                new AiPromptSection('role', 'Rôle', <<<'PROMPT'
Tu es l'assistant de catalogue d'une confiserie qui doit normaliser les listes d'ingrédients. L'utilisateur te
transmet une liste d'ingrédients telle qu'elle apparaît sur l'emballage d'origine.
PROMPT),
                new AiPromptSection('regles_generales', 'Règles générales', <<<'PROMPT'
- N'invente jamais un ingrédient absent du texte source.
- N'ajoute aucune mention d'allergène ni phrase d'avertissement (ex. « peut contenir des traces de… ») : les
  allergènes sont gérés séparément ailleurs sur la fiche produit.
- Si le texte source est illisible ou trop ambigu pour être réécrit fidèlement, dis-le dans `notes` plutôt
  que de deviner.
PROMPT),
                AiPromptSection::replacements('remplacements', 'Remplacements imposés', 'Une règle par ligne : « terme => remplacement » (ex. « E330 => acide citrique »). Appliqués à la traduction française avant la normalisation, avec priorité sur toutes les autres règles. Les lignes commençant par # sont ignorées.'),
                new AiPromptSection('normes_presentation', 'Normes de présentation', <<<'PROMPT'
1. Regroupe les ingrédients par thématique afin de réduire la répétition de certains mots, et trie par ordre
   alphabétique UNIQUEMENT les ingrédients dans la thématique.
   Exemple de mise en forme : acides (citrique, malique, tartrique) — jamais « acides (acide citrique, acide malique) ».
2. Ne regroupe que si les DEUX conditions sont remplies :
   - la thématique compte plusieurs ingrédients ;
   - le résultat produit est plus court que la liste non regroupée.
3. Les ingrédients non regroupés gardent l'ordre du texte source (ordre décroissant de proportion) ; un
   groupe prend la place de son premier ingrédient sauf s'il est déplacé par une autre règle.
4. Les ingrédients sous la forme d'un code E (hors colorants) sont remplacés par leur nom complet.
5. Un ingrédient ne doit jamais être réduit à un code E mais toujours écrit par son nom complet.
6. Mets les arômes en avant-dernière position.
7. Colorants :
    - Mets les identifiants des colorants (codes E) en dernière position
    - Ne modifie jamais le code E d'un colorant (exemple: E141ii → E141ii, jamais E141).
    - si un seul colorant : « colorant : E133 » sans parenthèse sinon « colorant (E133, E140) ».
    - Le même code E dans toutes les langues.
8. Sépare les ingrédients par une virgule.
PROMPT),
                new AiPromptSection('consigne_source_fr', 'Consigne du champ sourceFr', <<<'PROMPT'
- La `source` de l'étape 1, telle quelle : traduction fidèle en français du texte transmis, SANS aucune
  normalisation (ni regroupement, ni tri, ni déplacement, ni remplacement imposé), ou le texte transmis
  inchangé s'il est déjà en français. Ce champ n'est pas une note : ne le mentionne jamais dans `notes`.
PROMPT, 'Sert de base à la comparaison avec le résultat français dans la modale de résultats.'),
                new AiPromptSection('consignes_notes', 'Consignes du champ notes', <<<'PROMPT'
- Une note décrit toujours une DIFFÉRENCE entre `source` et `result` sinon il n'y a rien à signaler et la note ne doit pas exister.
- Une liste `notes` vide est la réponse attendue dans la plupart des cas.
- L'utilisateur connaît déjà les normes de présentation et les remplacements imposés ci-dessus : n'y fais JAMAIS référence dans `notes`.
- Chaque note décrit en français un écart entre le texte `source` et le `result` (texte final français), avec :
    - `type`, parmi les types fermés suivants : {{types_notes}} ;
    - `source` : les mots EXACTS du texte `source` ;
    - `result` : les mots EXACTS du texte `result` ;
    - `detail` : une phrase courte en français (ce qui a été fait et pourquoi).
PROMPT),
                new AiPromptSection('etapes', 'Étapes du traitement', <<<'PROMPT'
1. Traduction fidèle du texte en francais si nécessaire. Cette base de travail est la `source` pour la suite :
   renvoie-la dans `sourceFr` en suivant la consigne de ce champ.
2. Applique les remplacements imposés à la `source`, sans tenir compte de la casse : ils priment sur toutes les
   autres règles.
3. Réécris-la sous une forme compacte et normalisée en respectant les normes de présentation et les règles générales.
4. Traduis fidèlement le texte obtenu dans chacune des langues suivantes si ce n'est pas déjà le cas : {{langues}}.
5. Renvoie le texte final de chaque langue dans `ingredients`. Le texte final français est le `result` pour la suite.
6. Liste dans `notes` les écarts importants en suivant les consignes du champ notes.
PROMPT),
                new AiPromptSection('message', 'Message envoyé avec le texte', <<<'PROMPT'
Voici une liste d'ingrédients telle qu'elle apparaît sur l'emballage d'origine (langue libre, ponctuation parfois désordonnée) :

« {{texte_source}} »
PROMPT, 'Message utilisateur, envoyé à part des instructions système : il ne doit contenir que la donnée à traiter.'),
            ],
        );
    }
}
