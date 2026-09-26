<?php

namespace Modules\Panneaux\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Product;
use Modules\Allergenes\Support\AllergenDeclaration;
use Modules\Panneaux\Support\PanelSettings;
use Modules\Support\Modules;

/**
 * Un panneau : ses réglages d'affichage (colonne `settings`), ses blocs, et les bonbons qui y figurent
 * (avec, pour chacun, actif = en stock et DLUO — propres à ce panneau).
 */
#[Fillable(['name', 'location', 'notes', 'settings'])]
class Panel extends Model
{
    protected $table = 'panels';

    protected static function booted(): void
    {
        // Tout panneau démarre avec ses blocs système (marques, légende) et deux blocs d'info usuels.
        static::created(fn (Panel $panel) => $panel->ensureSystemBlocks());
    }

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    /** Tous les blocs dans l'ordre : avant les étiquettes, étiquettes, après. */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)
            ->orderByRaw("case zone when 'before' then 0 when 'labels' then 1 else 2 end")
            ->orderBy('position')
            ->orderBy('id');
    }

    /** Bonbons présents sur ce panneau (pivot : active, dluo). */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::modelClass(), 'panel_products')->withPivot(['active', 'dluo'])->withTimestamps();
    }

    /** @return array<string, mixed> */
    public function boardSettings(): array
    {
        return PanelSettings::merge($this->settings);
    }

    /** Crée les blocs système manquants (idempotent). */
    public function ensureSystemBlocks(): void
    {
        $defaults = [
            ['code' => Block::KIND_MARK, 'kind' => Block::KIND_MARK, 'title' => 'MARQUES DISPONIBLES EN VRAC', 'width' => 6, 'options' => ['showPercent' => true]],
            ['code' => Block::KIND_LEGEND, 'kind' => Block::KIND_LEGEND, 'title' => 'LÉGENDE DES COULEURS', 'width' => 6],
        ];

        // Contenu de départ du bloc légal, généré depuis le module Allergènes (régénérable ensuite depuis
        // la fenêtre d'édition du bloc, bouton « Générer depuis le module Allergènes »).
        $lawContent = Modules::enabled('Allergenes') ? AllergenDeclaration::html() : '';
        if ($lawContent === '') {
            $lawContent = __('panneaux::board.law_text').'<hr><small>'.__('panneaux::board.law_source').'</small>';
        }

        // Ces deux blocs d'information sont modifiables et supprimables (blocs personnalisés).
        $custom = [
            ['code' => 'custom-allergen', 'title' => __('panneaux::board.composition_title'), 'content' => __('panneaux::board.composition_text')],
            ['code' => 'custom-law', 'title' => __('panneaux::board.law_title'), 'content' => $lawContent],
        ];

        $order = (int) $this->blocks()->where('zone', 'before')->max('position');

        foreach ($defaults as $block) {
            if (! $this->blocks()->where('code', $block['code'])->exists()) {
                $this->blocks()->create([...$block, 'zone' => 'before', 'position' => ++$order]);
            }
        }

        if (! $this->blocks()->where('kind', Block::KIND_CUSTOM)->exists() && ! $this->blocks()->where('code', 'custom-allergen')->exists()) {
            foreach ($custom as $block) {
                $this->blocks()->create([...$block, 'kind' => Block::KIND_CUSTOM, 'zone' => 'before', 'width' => 6, 'position' => ++$order]);
            }
        }
    }
}
