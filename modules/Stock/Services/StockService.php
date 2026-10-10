<?php

namespace Modules\Stock\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Models\StockSetting;

/**
 * Le stock d'un bonbon est compté en KG entiers (colonne `stock` du variant Lunar). Deux conditionnements, propres au
 * fournisseur principal du bonbon (sinon les réglages par défaut) :
 * - le SAC, conditionnement de vente : une sortie se compte en sacs, chacun déduit son poids (sac entamé = sac déduit) ;
 * - le CARTON, conditionnement d'achat : les réceptions se saisissent en cartons.
 * L'historique garde la saisie d'origine (unité et quantité).
 */
class StockService
{
    /** Vrai pendant qu'un mouvement est enregistré ici, pour que l'observateur n'en crée pas un second. */
    public static bool $recording = false;

    public const UNIT_KG = 'kg';

    public const UNIT_BAG = 'bag';

    public const UNIT_CARTON = 'carton';

    /** @var (Closure(ProductVariant): array{bag_kg: ?int, carton_kg: ?int})|null conditionnements fournis par un autre module (ex. Fournisseurs) */
    private static ?Closure $packagingResolver = null;

    /** Le module qui connaît les conditionnements (fournisseurs) les fournit ici ; null = réglages par défaut. */
    public static function resolvePackagingUsing(?Closure $resolver): void
    {
        self::$packagingResolver = $resolver;
    }

    /** Poids d'un sac du bonbon (conditionnement de vente), en kg. */
    public static function kgPerBag(ProductVariant $variant): int
    {
        return max(1, (int) (self::packaging($variant)['bag_kg'] ?: self::defaults()['bag_kg']));
    }

    /** Contenu d'un carton du bonbon (conditionnement d'achat), en kg. */
    public static function kgPerCarton(ProductVariant $variant): int
    {
        return max(1, (int) (self::packaging($variant)['carton_kg'] ?: self::defaults()['carton_kg']));
    }

    /**
     * Conditionnements quand le fournisseur ne les précise pas (réglages du stock), mémorisés pour la requête.
     *
     * @return array{bag_kg: int, carton_kg: int}
     */
    public static function defaults(): array
    {
        return once(function () {
            $setting = StockSetting::current();

            return ['bag_kg' => max(1, $setting->default_bag_kg), 'carton_kg' => max(1, $setting->default_carton_kg)];
        });
    }

    /** Seuil d'alerte du produit, en kg. */
    public static function minStock(ProductVariant $variant): int
    {
        return max(0, (int) $variant->product?->attr('min_stock_kg'));
    }

    /** Convertit une saisie (kg, sacs ou cartons) en kg. */
    public static function toKg(ProductVariant $variant, string $unit, int $quantity): int
    {
        return match ($unit) {
            self::UNIT_CARTON => $quantity * self::kgPerCarton($variant),
            self::UNIT_BAG => $quantity * self::kgPerBag($variant),
            default => $quantity,
        };
    }

    /** Stock exprimé en [cartons entiers, kg restants]. */
    public static function breakdown(int $kg, int $perCarton): array
    {
        $kg = max(0, $kg);
        $perCarton = max(1, $perCarton);

        return [intdiv($kg, $perCarton), $kg % $perCarton];
    }

    /** @return array{bag_kg: ?int, carton_kg: ?int} */
    private static function packaging(ProductVariant $variant): array
    {
        return self::$packagingResolver ? (self::$packagingResolver)($variant) : ['bag_kg' => null, 'carton_kg' => null];
    }

    /** Efface tout l'historique et remet le stock de chaque bonbon à zéro. Retourne le nombre de mouvements supprimés. */
    public function reset(): int
    {
        return DB::transaction(function () {
            $deleted = StockMovement::query()->delete();

            // Mise à jour en masse : ne passe pas par l'observateur qui journalise les corrections manuelles.
            ProductVariant::withTrashed()->where('stock', '!=', 0)->update(['stock' => 0]);

            return $deleted;
        });
    }

    /**
     * Applique un mouvement de $kg kg (signé) de façon atomique.
     *
     * @throws InsufficientStock si le stock deviendrait négatif
     */
    public function move(
        ProductVariant $variant,
        int $kg,
        StockReason $reason,
        ?int $staffId = null,
        ?string $inputUnit = null,
        ?int $inputQuantity = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($variant, $kg, $reason, $staffId, $inputUnit, $inputQuantity, $note) {
            $locked = ProductVariant::withTrashed()->lockForUpdate()->findOrFail($variant->getKey());
            $stockAfter = $locked->stock + $kg;

            if ($stockAfter < 0) {
                $requested = abs($kg);

                throw new InsufficientStock("Stock insuffisant : {$locked->stock} kg disponible(s), {$requested} kg demandé(s).");
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
                'quantity' => $kg,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'input_unit' => $inputUnit,
                'input_quantity' => $inputQuantity,
                'note' => $note,
            ]);
        });
    }
}
