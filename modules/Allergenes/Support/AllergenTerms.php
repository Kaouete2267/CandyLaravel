<?php

namespace Modules\Allergenes\Support;

use Illuminate\Support\Str;

/**
 * Reconnaissance des allergènes dans une liste d'ingrédients en français (mots-clés + mention « peut contenir… »),
 * comme le faisait l'ancienne application. Sert à déduire les allergènes d'un produit (import) et à les surligner
 * sur le panneau imprimé.
 *
 * - avant la mention « peut contenir… / fabriqué dans… » : l'allergène est **contenu** ;
 * - dans la mention : ce sont des **traces**.
 * Les codes sont ceux du module (gluten, milk, eggs, soy, peanuts, tree_nuts…).
 *
 * La recherche se fait sur le texte sans accents ni casse (« farine de BLE » = « farine de blé »),
 * les motifs ci-dessous sont donc écrits sans accents.
 */
class AllergenTerms
{
    /** code allergène => motif (sans délimiteurs, sans accents) des mots qui le désignent. */
    public const TERMS = [
        'gluten' => 'ble|froment|orge|seigle|avoine|epeautre|kamut|gluten',
        'milk' => 'lait(?! de coco)|lactose|lactoserum|lactes?|caseine|beurre(?! de (?:cacao|karite|cacahuetes?))|creme(?! de tartre)',
        'eggs' => 'oeufs?|ovalbumine',
        'soy' => 'soja',
        'peanuts' => 'arachides?|cacahuetes?',
        // La noix de coco, de palmiste et de muscade ne sont pas des fruits à coque au sens de la réglementation.
        'tree_nuts' => 'fruits? a coques?|amandes?|noisettes?|noix(?! de (?:coco|palmiste|muscade))|pistaches?|cajou|pecans?|macadamia',
        'sesame' => 'sesame',
        'sulphites' => 'sulfites?|anhydride sulfureux|e22[0-8]',
        'celery' => 'celeri',
        'mustard' => 'moutarde',
        'lupin' => 'lupin',
        'fish' => 'poisson',
        'crustaceans' => 'crustaces',
        'molluscs' => 'mollusques',
    ];

    /** Début de la mention de traces (elle court jusqu'à la fin du texte). */
    public const MENTION = '~(?<![a-z])(?:peut contenir|peuvent contenir|fabriquee? dans|traces? (?:eventuelles? )?d[e\']|produit dans)~';

    /** Texte sans accents, en minuscules (œ → oe, ’ → '). */
    public static function normalize(string $text): string
    {
        return Str::of(str_replace('’', "'", $text))->ascii()->lower()->toString();
    }

    /**
     * @return array{contains: array<int,string>, may_contain: array<int,string>, notes: array<int,string>}
     */
    public static function analyze(string $ingredients): array
    {
        $text = str_replace('sans gluten', '', self::normalize($ingredients));

        $body = $text;
        $mention = '';

        if (preg_match(self::MENTION, $text, $m, PREG_OFFSET_CAPTURE)) {
            $body = substr($text, 0, $m[0][1]);
            $mention = substr($text, $m[0][1]);
        }

        $contains = self::codesIn($body);
        $mayContain = array_values(array_diff(self::codesIn($mention), $contains));

        $notes = [];
        if (! in_array('gluten', $contains, true) && preg_match('~(?<![a-z])malt(?![a-z])~', $body)) {
            $notes[] = 'contient du malt (gluten possible s\'il est d\'orge) : à vérifier';
        }

        return ['contains' => $contains, 'may_contain' => $mayContain, 'notes' => $notes];
    }

    /** @return array<int, string> */
    private static function codesIn(string $normalized): array
    {
        if ($normalized === '') {
            return [];
        }

        $found = [];
        foreach (self::TERMS as $code => $pattern) {
            if (preg_match(self::wordPattern($pattern), $normalized)) {
                $found[] = $code;
            }
        }

        return $found;
    }

    private static function wordPattern(string $pattern): string
    {
        return '~(?<![a-z\d])(?:'.$pattern.')(?![a-z])~';
    }

    /**
     * Texte des ingrédients en HTML sûr, avec les allergènes surlignés :
     * `pb-a-contains` (contenu), `pb-a-trace` (dans la mention de traces), `pb-a-free` (« sans gluten »),
     * et la mention de traces entière dans un `pb-mention`.
     */
    public static function highlight(string $ingredients): string
    {
        $chars = mb_str_split($ingredients);

        // Texte normalisé + table normalisé -> index du caractère d'origine (œ = 2 lettres, etc.).
        $normalized = '';
        $map = [];
        foreach ($chars as $index => $char) {
            $ascii = strtolower(Str::ascii(str_replace('’', "'", $char)));
            $ascii = $ascii === '' ? ' ' : $ascii;

            for ($k = 0, $n = strlen($ascii); $k < $n; $k++) {
                $map[strlen($normalized) + $k] = $index;
            }
            $normalized .= $ascii;
        }
        $map[strlen($normalized)] = count($chars);

        $mentionFrom = null;
        if (preg_match(self::MENTION, $normalized, $m, PREG_OFFSET_CAPTURE)) {
            $mentionFrom = $map[$m[0][1]];
        }

        // [début, fin exclue, classe] en indices de caractères d'origine
        $ranges = [];
        $collect = function (string $regex, ?string $class) use ($normalized, $map, $mentionFrom, &$ranges) {
            if (! preg_match_all($regex, $normalized, $matches, PREG_OFFSET_CAPTURE)) {
                return;
            }
            foreach ($matches[0] as [$text, $offset]) {
                $from = $map[$offset];
                $to = $map[$offset + strlen($text) - 1] + 1;
                $ranges[] = [$from, $to, $class ?? ($mentionFrom !== null && $from >= $mentionFrom ? 'trace' : 'contains')];
            }
        };

        foreach (self::TERMS as $pattern) {
            $collect(self::wordPattern($pattern), null);
        }
        $collect('~sans gluten~', 'free');

        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);

        $html = '';
        $cursor = 0;
        $mentionOpen = false;
        $emit = function (int $from, int $to) use ($chars, &$html, &$mentionOpen, $mentionFrom) {
            // Ouvre la mention de traces au bon endroit, même au milieu d'un segment neutre.
            if ($mentionFrom !== null && ! $mentionOpen && $mentionFrom >= $from && $mentionFrom < $to) {
                $html .= e(implode('', array_slice($chars, $from, $mentionFrom - $from))).'<span class="pb-mention">';
                $mentionOpen = true;
                $from = $mentionFrom;
            }
            $html .= e(implode('', array_slice($chars, $from, $to - $from)));
        };

        foreach ($ranges as [$from, $to, $class]) {
            if ($from < $cursor) {
                continue;   // chevauchement : le premier trouvé gagne
            }
            $emit($cursor, $from);
            if ($mentionFrom !== null && ! $mentionOpen && $from >= $mentionFrom) {
                $html .= '<span class="pb-mention">';
                $mentionOpen = true;
            }
            $html .= '<mark class="pb-a pb-a-'.$class.'">'.e(implode('', array_slice($chars, $from, $to - $from))).'</mark>';
            $cursor = $to;
        }
        $emit($cursor, count($chars));

        return $html.($mentionOpen ? '</span>' : '');
    }
}
