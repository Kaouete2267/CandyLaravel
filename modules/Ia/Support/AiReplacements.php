<?php

namespace Modules\Ia\Support;

/**
 * Remplacements imposés à l'IA, saisis une règle par ligne (« terme => remplacement ») dans une section
 * {@see AiPromptSection::replacements()} : pas de CRUD, la liste vit dans le texte de la section. Les lignes
 * vides et celles commençant par « # » (commentaires) sont ignorées.
 */
class AiReplacements
{
    public const SEPARATOR = '=>';

    /** @return array<int, array{from: string, to: string}> */
    public static function parse(string $text): array
    {
        $rules = [];
        foreach (self::ruleLines($text) as $line) {
            [$from, $to] = array_map('trim', explode(self::SEPARATOR, $line, 2)) + [1 => ''];

            if ($from !== '' && $to !== '') {
                $rules[] = ['from' => $from, 'to' => $to];
            }
        }

        return $rules;
    }

    /**
     * Lignes qui ne respectent pas le format, pour la validation du formulaire.
     *
     * @return array<int, string>
     */
    public static function invalidLines(string $text): array
    {
        return array_values(array_filter(self::ruleLines($text), function (string $line) {
            $parts = array_map('trim', explode(self::SEPARATOR, $line, 2));

            return count($parts) !== 2 || $parts[0] === '' || $parts[1] === '';
        }));
    }

    /** Texte inséré dans le prompt : une puce par règle, « (aucun) » s'il n'y en a pas. */
    public static function toPrompt(string $text): string
    {
        $rules = self::parse($text);

        if ($rules === []) {
            return '(aucun)';
        }

        return implode("\n", array_map(fn (array $rule) => "- « {$rule['from']} » → « {$rule['to']} »", $rules));
    }

    /** @return array<int, string> */
    private static function ruleLines(string $text): array
    {
        $lines = array_map('trim', preg_split('/\R/', $text) ?: []);

        return array_values(array_filter($lines, fn (string $line) => $line !== '' && ! str_starts_with($line, '#')));
    }
}
