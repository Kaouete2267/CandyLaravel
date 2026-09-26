<?php

namespace Modules\Stock\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;

/**
 * Le stock d'un bonbon est compté en SACS (colonne `stock` du variant Lunar).
 * Les entrées peuvent être saisies en cartons : on convertit avec « sacs par carton »
 * (attribut produit) et l'historique garde la saisie d'origine.
 */
class StockService
{
    /** Vrai pendant qu'un mouvement est enregistré ici, pour que l'observateur n'en crée pas un second. */
    public static bool $recording = false;

    public const UNIT_BAG = 'bag';

    public const UNIT_CARTON = 'carton';

    /** Nombre de sacs par carton du produit (1 si non renseigné). */
    public static function bagsPerCarton(ProductVariant $variant): int
    {
        return max(1, (int) $variant->product?->attr('bags_per_carton'));
    }

    /** Seuil d'alerte du produit, en sacs. */
    public static function minStock(ProductVariant $variant): int
    {
        return max(0, (int) $variant->product?->attr('min_stock_bags'));
    }

    /** Convertit une saisie (sacs ou cartons) en sacs. */
    public static function toBags(ProductVariant $variant, string $unit, int $quantity): int
    {
        return $unit === self::UNIT_CARTON ? $quantity * self::bagsPerCarton($variant) : $quantity;
    }

    /** Stock exprimé en [cartons entiers, sacs restants]. */
    public static function breakdown(int $bags, int $perCarton): array
    {
        $bags = max(0, $bags);
        $perCarton = max(1, $perCarton);

        return [intdiv($bags, $perCarton), $bags % $perCarton];
    }

    /**
     * Applique un mouvement de $bags sacs (signé) de façon atomique.
     *
     * @throws InsufficientStock si le stock deviendrait négatif
     */
    public function move(
        ProductVariant $variant,
        int $bags,
        StockReason $reason,
        ?int $staffId = null,
        ?string $inputUnit = null,
        ?int $inputQuantity = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $bags, $reason, $staffId, $inputUnit, $inputQuantity, $note) {
            $locked = ProductVariant::withTrashed()->lockForUpdate()->findOrFail($variant->getKey());
            $stockAfter = $locked->stock + $bags;

            if ($stockAfter < 0) {
                $requested = abs($bags);

                throw new InsufficientStock("Stock insuffisant : {$locked->stock} sac(s) disponible(s), {$requested} demandé(s).");
            }

            self::$recording = true;
            try {
                $locked->stock = $stockAfter;
                $locked->save();
            } finally {
                self::$recording = false;
            }

            $variant->stock = $stockAfter;

            return StockMovement::create([
                'product_variant_id' => $locked->id,
                'staff_id' => $staffId,
                'quantity' => $bags,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'input_unit' => $inputUnit,
                'input_quantity' => $inputQuantity,
                'note' => $note,
            ]);
        });
    }
}
