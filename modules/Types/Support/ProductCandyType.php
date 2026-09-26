<?php

namespace Modules\Types\Support;

use Illuminate\Database\Eloquent\Model;

/** Type d'un produit Lunar (relation `candyType` ajoutée par le module ; au plus un type par produit). */
class ProductCandyType
{
    public static function id(Model $product): ?int
    {
        return $product->candyType()->first()?->id;
    }

    /** Fixe le type du produit, ou le retire si $typeId est vide. */
    public static function assign(Model $product, int|string|null $typeId): void
    {
        if (blank($typeId)) {
            $product->candyType()->detach();

            return;
        }

        $product->candyType()->sync([(int) $typeId]);
    }
}
