<?php

namespace Modules\Ia\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Ia\Services\IngredientGlossary;
use Spatie\Translatable\HasTranslations;

/**
 * Une entrée du glossaire d'ingrédients (voir la migration create_ingredient_terms_table) : la catégorie et
 * le texte final associés à un ingrédient déjà rencontré, retrouvés par {@see IngredientGlossary}
 * via ses alias (formes françaises normalisées détectées jusqu'ici).
 */
class IngredientTerm extends Model
{
    use HasTranslations;

    protected $table = 'ingredient_terms';

    protected $fillable = ['category', 'aliases', 'name', 'reviewed_at'];

    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
