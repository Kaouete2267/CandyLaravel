<?php

namespace Modules\Stock\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Once;

/**
 * Réglages du stock saisis depuis le backoffice (ligne unique).
 * Conditionnements par défaut, quand le fournisseur du bonbon ne les précise pas :
 * `default_bag_kg` : poids d'un sac (conditionnement de vente, base des sorties de stock) ;
 * `default_carton_kg` : contenu d'un carton (conditionnement d'achat).
 */
#[Fillable(['default_bag_kg', 'default_carton_kg'])]
class StockSetting extends Model
{
    public const DEFAULT_BAG_KG = 1;

    public const DEFAULT_CARTON_KG = 3;

    protected $table = 'stock_settings';

    protected static function booted(): void
    {
        // StockService::defaults() mémorise les valeurs pour la requête (once()).
        static::saved(fn () => Once::flush());
    }

    protected function casts(): array
    {
        return ['default_bag_kg' => 'integer', 'default_carton_kg' => 'integer'];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['default_bag_kg' => self::DEFAULT_BAG_KG, 'default_carton_kg' => self::DEFAULT_CARTON_KG]);
    }
}
