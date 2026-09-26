<?php

namespace Modules\Panneaux\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lunar\Models\Brand;
use Lunar\Models\Product;

/**
 * Bloc d'un panneau. Quatre sortes :
 *  - `brand`  : bloc d'étiquettes ALIMENTÉ AUTOMATIQUEMENT par une marque (les bonbons de cette marque présents
 *               sur le panneau) ; brand_id vide = « sans marque » ;
 *  - `manual` : bloc d'étiquettes dont on choisit soi-même les bonbons et leur ordre ;
 *  - `native-mark` / `native-legend` : blocs SYSTÈME d'information (marques disponibles, légende des types),
 *               modifiables mais non supprimables ;
 *  - `custom` : bloc d'information libre (titre + contenu HTML).
 * Les blocs d'information s'affichent avant ou après les étiquettes, sur une grille de 12 colonnes, et peuvent
 * être répétés sur chaque page.
 */
#[Fillable([
    'panel_id', 'kind', 'brand_id', 'zone', 'active', 'code', 'name', 'title', 'content',
    'position', 'width', 'repeat', 'show_header', 'options',
])]
class Block extends Model
{
    public const GRID_COLUMNS = 12;

    public const KIND_BRAND = 'brand';

    public const KIND_MANUAL = 'manual';

    public const KIND_MARK = 'native-mark';

    public const KIND_LEGEND = 'native-legend';

    public const KIND_CUSTOM = 'custom';

    protected $table = 'blocks';

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'repeat' => 'boolean',
            'show_header' => 'boolean',
            'options' => 'array',
            'width' => 'integer',
            'position' => 'integer',
        ];
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** Bonbons choisis à la main (blocs « manual »), dans leur ordre. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::modelClass(), 'block_product')->withPivot('slot')->orderByPivot('slot');
    }

    public function isSystem(): bool
    {
        return in_array($this->kind, [self::KIND_MARK, self::KIND_LEGEND], true);
    }

    /** Bloc d'information (avant / après les étiquettes) plutôt que bloc d'étiquettes. */
    public function isInfo(): bool
    {
        return $this->isSystem() || $this->kind === self::KIND_CUSTOM;
    }

    public function isAuto(): bool
    {
        return $this->kind === self::KIND_BRAND;
    }

    public function isDeletable(): bool
    {
        return ! $this->isSystem();
    }

    /** Titre affiché : celui saisi, sinon le nom (marque…) du bloc. */
    public function displayTitle(): string
    {
        return (string) ($this->title ?: ($this->name ?: ($this->kind === self::KIND_BRAND && ! $this->brand_id ? 'Sans marque' : '')));
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return ($this->options ?? [])[$key] ?? $default;
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_BRAND => 'Marque (automatique)',
            self::KIND_MANUAL => 'Manuel',
            self::KIND_MARK => 'Système : marques',
            self::KIND_LEGEND => 'Système : légende',
            default => 'Information',
        };
    }
}
