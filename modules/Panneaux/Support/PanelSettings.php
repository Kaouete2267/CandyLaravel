<?php

namespace Modules\Panneaux\Support;

/**
 * Réglages d'affichage d'UN panneau (colonne `panels.settings`), même forme que l'ancienne application :
 * page (mise en page), titre, bonbon (étiquettes), info (zone d'information), showDluo. Une police se
 * note [famille, taille en px, couleur]. Les allergènes à surligner viennent du module Allergènes
 * (mots-clés communs à tout le catalogue) : ce n'est plus un réglage par panneau (voir AllergenTerms).
 */
class PanelSettings
{
    public const DEFAULTS = [
        'showDluo' => true,
        'page' => [
            'orientation' => 'portrait',   // portrait | paysage
            'columnsPerRow' => 3,          // colonnes d'étiquettes par page : 2 à 6
            'dluo' => false,               // page récapitulative des DLUO (si showDluo)
        ],
        'titre' => [
            'content' => '',
            'font' => 'Arial Black',
            'size' => '48',
            'color' => '#c2185b',
            'bordercolor' => '#333333',
            'ononepage' => true,           // titre sur la première page seulement (sinon une ligne = une page)
        ],
        'bonbon' => [
            'bordercolor' => '#dddddd',
            'name' => ['Times New Roman', '13', '#000000'],
            'ing' => ['Times New Roman', '10', '#555555'],
            'dluo' => ['Times New Roman', '10', '#555555'],
            'allergene' => 'background',   // background (surligné) | color (texte coloré)
        ],
        'info' => [
            'bordercolor' => '#e4f0f6',
            'title' => ['Times New Roman', '13', '#ffffff'],
            'content' => ['Times New Roman', '10', '#eeeeee'],
            'bgcolor' => ['head' => '#2e79b4', 'body' => '#4593d0'],
        ],
    ];

    public const ORIENTATIONS = ['portrait' => 'Portrait', 'paysage' => 'Paysage'];

    public const COLUMNS_PER_ROW = [2, 3, 4, 5, 6];

    public const FONTS = [
        'Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Georgia', 'Impact', 'Lucida Console',
        'Lucida Sans Unicode', 'Palatino Linotype', 'Tahoma', 'Times New Roman', 'Trebuchet MS', 'Verdana',
    ];

    /** @return array<string, mixed> valeurs enregistrées complétées par les valeurs par défaut */
    public static function merge(?array $stored): array
    {
        return array_replace_recursive(self::DEFAULTS, $stored ?? []);
    }

    /** Valide et complète des réglages saisis : rien de ce qui vient d'un formulaire n'est pris tel quel. */
    public static function sanitize(array $in): array
    {
        $d = self::DEFAULTS;
        $color = fn ($v, $default) => is_string($v) && preg_match('/^(#[0-9a-f]{3,8}|[a-z]{3,20})$/i', trim($v)) ? trim($v) : $default;
        $font = fn ($v, $default) => [
            in_array($v[0] ?? null, self::FONTS, true) ? $v[0] : $default[0],
            (string) max(6, min(200, (int) ($v[1] ?? $default[1]))),
            $color($v[2] ?? null, $default[2]),
        ];

        return [
            'showDluo' => (bool) ($in['showDluo'] ?? $d['showDluo']),
            'page' => [
                'orientation' => array_key_exists($in['page']['orientation'] ?? null, self::ORIENTATIONS) ? $in['page']['orientation'] : $d['page']['orientation'],
                'columnsPerRow' => in_array((int) ($in['page']['columnsPerRow'] ?? 0), self::COLUMNS_PER_ROW, true) ? (int) $in['page']['columnsPerRow'] : $d['page']['columnsPerRow'],
                'dluo' => (bool) ($in['page']['dluo'] ?? $d['page']['dluo']),
            ],
            'titre' => [
                'content' => mb_substr((string) ($in['titre']['content'] ?? ''), 0, 500),
                'font' => in_array($in['titre']['font'] ?? null, self::FONTS, true) ? $in['titre']['font'] : $d['titre']['font'],
                'size' => (string) max(8, min(300, (int) ($in['titre']['size'] ?? $d['titre']['size']))),
                'color' => $color($in['titre']['color'] ?? null, $d['titre']['color']),
                'bordercolor' => $color($in['titre']['bordercolor'] ?? null, $d['titre']['bordercolor']),
                'ononepage' => (bool) ($in['titre']['ononepage'] ?? $d['titre']['ononepage']),
            ],
            'bonbon' => [
                'bordercolor' => $color($in['bonbon']['bordercolor'] ?? null, $d['bonbon']['bordercolor']),
                'name' => $font($in['bonbon']['name'] ?? [], $d['bonbon']['name']),
                'ing' => $font($in['bonbon']['ing'] ?? [], $d['bonbon']['ing']),
                'dluo' => $font($in['bonbon']['dluo'] ?? [], $d['bonbon']['dluo']),
                'allergene' => in_array($in['bonbon']['allergene'] ?? null, ['background', 'color'], true) ? $in['bonbon']['allergene'] : $d['bonbon']['allergene'],
            ],
            'info' => [
                'bordercolor' => $color($in['info']['bordercolor'] ?? null, $d['info']['bordercolor']),
                'title' => $font($in['info']['title'] ?? [], $d['info']['title']),
                'content' => $font($in['info']['content'] ?? [], $d['info']['content']),
                'bgcolor' => [
                    'head' => $color($in['info']['bgcolor']['head'] ?? null, $d['info']['bgcolor']['head']),
                    'body' => $color($in['info']['bgcolor']['body'] ?? null, $d['info']['bgcolor']['body']),
                ],
            ],
        ];
    }
}
