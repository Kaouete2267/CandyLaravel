<?php

namespace Modules\Ia\Support;

use Modules\Ia\Services\IngredientsWriter;

/**
 * Catégories CHIMIQUES (jamais fonctionnelles : pas d'"antioxydants", pas de "conservateurs"…) utilisées
 * pour regrouper une liste d'ingrédients. Un ingrédient sans catégorie (ex. « sucre », « farine de blé »)
 * reste isolé : {@see IngredientsWriter} ne lui assigne aucune valeur de cet enum.
 */
enum IngredientCategory: string
{
    case Sirops = 'sirops';
    case Amidons = 'amidons';
    case Acides = 'acides';
    case Concentres = 'concentres';
    case Cires = 'cires';
    case HuilesGraisses = 'huiles_graisses';
    case Extraits = 'extraits';
    case Gommes = 'gommes';
    case Aromes = 'aromes';
    case Colorants = 'colorants';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Libellé du groupe, au singulier pour un seul sous-ingrédient (« acide citrique »), au pluriel avec
     * parenthèses pour plusieurs (« acides (citrique, malique) ») — sauf les colorants, qui gardent leur
     * forme historique à deux points au singulier (« colorant : E133 »), gérée à part par l'appelant.
     */
    public function label(string $locale, bool $plural): string
    {
        return self::LABELS[$this->value][$plural ? 'plural' : 'singular'][$locale]
            ?? self::LABELS[$this->value][$plural ? 'plural' : 'singular']['fr'];
    }

    /** @var array<string, array{singular: array<string,string>, plural: array<string,string>}> */
    private const LABELS = [
        'sirops' => [
            'singular' => ['fr' => 'sirop', 'nl' => 'siroop', 'en' => 'syrup'],
            'plural' => ['fr' => 'sirops', 'nl' => 'siropen', 'en' => 'syrups'],
        ],
        'amidons' => [
            'singular' => ['fr' => 'amidon', 'nl' => 'zetmeel', 'en' => 'starch'],
            'plural' => ['fr' => 'amidons', 'nl' => 'zetmeel', 'en' => 'starches'],
        ],
        'acides' => [
            'singular' => ['fr' => 'acide', 'nl' => 'zuur', 'en' => 'acid'],
            'plural' => ['fr' => 'acides', 'nl' => 'zuren', 'en' => 'acids'],
        ],
        'concentres' => [
            'singular' => ['fr' => 'concentré', 'nl' => 'concentraat', 'en' => 'concentrate'],
            'plural' => ['fr' => 'concentrés', 'nl' => 'concentraten', 'en' => 'concentrates'],
        ],
        'cires' => [
            'singular' => ['fr' => 'cire', 'nl' => 'was', 'en' => 'wax'],
            'plural' => ['fr' => 'cires', 'nl' => 'wassen', 'en' => 'waxes'],
        ],
        'huiles_graisses' => [
            'singular' => ['fr' => 'huile/graisse', 'nl' => 'olie/vet', 'en' => 'oil/fat'],
            'plural' => ['fr' => 'huiles/graisses', 'nl' => 'oliën/vetten', 'en' => 'oils/fats'],
        ],
        'extraits' => [
            'singular' => ['fr' => 'extrait', 'nl' => 'extract', 'en' => 'extract'],
            'plural' => ['fr' => 'extraits', 'nl' => 'extracten', 'en' => 'extracts'],
        ],
        'gommes' => [
            'singular' => ['fr' => 'gomme', 'nl' => 'gom', 'en' => 'gum'],
            'plural' => ['fr' => 'gommes', 'nl' => 'gommen', 'en' => 'gums'],
        ],
        'aromes' => [
            'singular' => ['fr' => 'arôme', 'nl' => 'aroma', 'en' => 'flavour'],
            'plural' => ['fr' => 'arômes', 'nl' => 'aroma\'s', 'en' => 'flavours'],
        ],
        'colorants' => [
            'singular' => ['fr' => 'colorant', 'nl' => 'kleurstof', 'en' => 'colour'],
            'plural' => ['fr' => 'colorants', 'nl' => 'kleurstoffen', 'en' => 'colours'],
        ],
    ];
}
