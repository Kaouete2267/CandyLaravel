<?php

namespace Modules\Types\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Models\Product;
use Spatie\Translatable\HasTranslations;

#[Fillable(['code', 'name', 'color', 'font_color', 'position'])]
class CandyType extends Model
{
    use HasTranslations;

    protected $table = 'candy_types';

    public array $translatable = ['name'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::modelClass(), 'candy_type_product');
    }
}
