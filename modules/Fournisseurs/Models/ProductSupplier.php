<?php

namespace Modules\Fournisseurs\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Lunar\Models\Product;
use Modules\Fournisseurs\Services\SupplierCatalog;

/**
 * Un bonbon chez un fournisseur : référence, poids d'un sac (vente) et contenu d'un carton (achat), sinon ceux du
 * fournisseur, prix d'achat HT d'un carton, et s'il s'agit du fournisseur principal du bonbon.
 */
#[Fillable(['product_id', 'supplier_id', 'reference', 'bag_kg', 'carton_kg', 'carton_price', 'is_main'])]
class ProductSupplier extends Pivot
{
    protected $table = 'product_supplier';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'bag_kg' => 'integer',
            'carton_kg' => 'integer',
            'carton_price' => 'decimal:2',
            'is_main' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Un seul fournisseur principal par bonbon : le dernier désigné l'emporte.
        static::saved(function (ProductSupplier $link) {
            if ($link->is_main) {
                static::query()->where('product_id', $link->product_id)->whereKeyNot($link->getKey())->update(['is_main' => false]);
            } else {
                static::ensureMainSupplier($link->product_id);
            }

            app(SupplierCatalog::class)->flush();
        });

        static::deleted(function (ProductSupplier $link) {
            static::ensureMainSupplier($link->product_id);
            app(SupplierCatalog::class)->flush();
        });
    }

    /** Un bonbon qui a des fournisseurs en a toujours un principal (le premier enregistré, à défaut). */
    public static function ensureMainSupplier(int $productId): void
    {
        $links = static::query()->where('product_id', $productId);

        if (! $links->clone()->where('is_main', true)->exists() && ($firstId = $links->clone()->orderBy('id')->value('id'))) {
            static::query()->whereKey($firstId)->update(['is_main' => true]);
        }
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::modelClass());
    }
}
