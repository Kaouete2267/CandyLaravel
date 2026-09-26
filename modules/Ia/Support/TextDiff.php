<?php

namespace Modules\Ia\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Modules\Ia\Filament\Actions\ReviewIngredientsDiff;

/**
 * Diff « par ingrédient » (segments séparés par une virgule, pas mot à mot : plus lisible pour une liste
 * d'ingrédients) entre un texte avant et un texte après, rendu en HTML avec les ajouts/suppressions/
 * déplacements surlignés — pour la modale de comparaison de {@see ReviewIngredientsDiff}.
 *
 * L'égalité se juge sans tenir compte de la casse ni des accents : l'IA normalise systématiquement la casse
 * (tout en minuscules), donc une différence de casse seule entre l'avant et l'après n'est jamais un vrai
 * changement de contenu — la compter comme tel noierait les vrais changements sous du bruit.
 */
class TextDiff
{
    public static function html(?string $before, ?string $after): HtmlString
    {
        $before = trim((string) $before);
        $after = trim((string) $after);

        if ($before === '' && $after === '') {
            return new HtmlString('<span class="ia-diff-empty">(vide)</span>');
        }

        $ops = array_values(array_filter(
            self::markMoves(self::diff(self::tokenize($before), self::tokenize($after))),
            fn (array $op) => $op[0] !== 'moved-from'
        ));

        $parts = array_map(
            fn (array $op) => match ($op[0]) {
                'del' => '<del class="ia-diff-del">'.e($op[1]).'</del>',
                'ins' => '<ins class="ia-diff-ins">'.e($op[1]).'</ins>',
                'move' => '<span class="ia-diff-move">'.e($op[1]).'</span>',
                default => e($op[1]),
            },
            $ops
        );

        return new HtmlString(implode('<span class="ia-diff-sep">, </span>', $parts));
    }

    /**
     * Découpe sur les virgules de premier niveau seulement : une virgule à l'intérieur d'une parenthèse fait
     * partie du même ingrédient (ex. "acides (citrique, malique)" est UN token, pas deux) — la couper
     * dédoublerait les groupes et désalignerait tout le diff dès qu'une catégorie contient plusieurs
     * sous-ingrédients.
     *
     * @return array<int, string>
     */
    private static function tokenize(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $tokens = [];
        $depth = 0;
        $current = '';

        foreach (mb_str_split($text) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            $depth = max($depth, 0);

            if ($char === ',' && $depth === 0) {
                $tokens[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }
        $tokens[] = trim($current);

        return array_values(array_filter($tokens, fn ($token) => $token !== ''));
    }

    /** Clé de comparaison : sans casse ni accents, pour ignorer les différences de normalisation pure. */
    private static function key(string $token): string
    {
        return Str::of($token)->ascii()->lower()->toString();
    }

    /**
     * Plus longue sous-séquence commune entre les deux listes de segments (comparée sur la clé normalisée),
     * pour ne surligner que ce qui a réellement changé plutôt que de tout marquer comme supprimé puis
     * réajouté. Le texte conservé pour un segment inchangé est celui de la version « après », puisque c'est
     * ce que le champ contient désormais.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array{0: 'same'|'del'|'ins', 1: string}>
     */
    private static function diff(array $a, array $b): array
    {
        $ak = array_map(self::key(...), $a);
        $bk = array_map(self::key(...), $b);

        $n = count($a);
        $m = count($b);

        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $ak[$i] === $bk[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($ak[$i] === $bk[$j]) {
                $ops[] = ['same', $b[$j]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['del', $a[$i]];
                $i++;
            } else {
                $ops[] = ['ins', $b[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['del', $a[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['ins', $b[$j]];
            $j++;
        }

        return $ops;
    }

    /**
     * Un ingrédient supprimé ici et ajouté ailleurs (même clé normalisée) n'est pas un vrai changement de
     * contenu, juste un déplacement (ex. réordonnancement alphabétique). Budgétisé par le nombre
     * d'occurrences des deux côtés pour ne pas sur-convertir en cas de doublons. Il n'est affiché qu'UNE
     * fois, à sa nouvelle position ("move") : le côté supprimé ("moved-from") est marqué pour être filtré
     * par {@see html()}, pour ne pas montrer deux fois le même ingrédient sous prétexte qu'il a bougé.
     *
     * @param  array<int, array{0: 'same'|'del'|'ins', 1: string}>  $ops
     * @return array<int, array{0: 'same'|'del'|'ins'|'move'|'moved-from', 1: string}>
     */
    private static function markMoves(array $ops): array
    {
        $delCounts = [];
        $insCounts = [];
        foreach ($ops as [$type, $text]) {
            $key = self::key($text);
            if ($type === 'del') {
                $delCounts[$key] = ($delCounts[$key] ?? 0) + 1;
            } elseif ($type === 'ins') {
                $insCounts[$key] = ($insCounts[$key] ?? 0) + 1;
            }
        }

        $pairBudget = [];
        foreach ($delCounts as $key => $count) {
            if (isset($insCounts[$key])) {
                $pairBudget[$key] = min($count, $insCounts[$key]);
            }
        }

        // Chaque paire budgète UNE conversion côté suppression ET UNE côté ajout : un compteur partagé
        // déciderait par erreur qu'une seule des deux moitiés de la paire a le droit de bouger.
        $delBudget = $pairBudget;
        $insBudget = $pairBudget;

        foreach ($ops as $i => [$type, $text]) {
            $key = self::key($text);
            if ($type === 'del' && ($delBudget[$key] ?? 0) > 0) {
                $ops[$i][0] = 'moved-from';
                $delBudget[$key]--;
            } elseif ($type === 'ins' && ($insBudget[$key] ?? 0) > 0) {
                $ops[$i][0] = 'move';
                $insBudget[$key]--;
            }
        }

        return $ops;
    }
}
