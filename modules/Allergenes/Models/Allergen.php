<?php

namespace Modules\Allergenes\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Models\Product;
use Spatie\Translatable\HasTranslations;

#[Fillable(['code', 'name', 'position'])]
class Allergen extends Model
{
    use HasTranslations;

    protected $table = 'allergens';

    public array $translatable = ['name'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::modelClass(), 'allergen_product')->withPivot('type');
    }
}
