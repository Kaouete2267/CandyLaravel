<?php

namespace Modules\Fournisseurs\Services;

use Modules\Fournisseurs\Models\ProductSupplier;

/**
 * Fournisseur principal de chaque bonbon, chargé une fois par requête : conditionnements (poids d'un sac pour la vente,
 * contenu d'un carton pour l'achat), prix d'un carton et nom, pour le stock et l'analyse des commandes.
 */
class SupplierCatalog
{
    /** @var array<int, array{supplier_id: int, name: string, bag_kg: ?int, carton_kg: ?int, carton_price: ?float}>|null par produit */
    private ?array $main = null;

    /** @return array{supplier_id: int, name: string, bag_kg: ?int, carton_kg: ?int, carton_price: ?float}|null */
    public function main(int $productId): ?array
    {
        return $this->all()[$productId] ?? null;
    }

    /**
     * Conditionnements chez le fournisseur principal (null quand ils ne sont pas connus).
     *
     * @return array{bag_kg: ?int, carton_kg: ?int}
     */
    public function packaging(int $productId): array
    {
        $main = $this->main($productId);

        return ['bag_kg' => $main['bag_kg'] ?? null, 'carton_kg' => $main['carton_kg'] ?? null];
    }

    /** @return array<int, array{supplier_id: int, name: string, bag_kg: ?int, carton_kg: ?int, carton_price: ?float}> */
    public function all(): array
    {
        return $this->main ??= ProductSupplier::query()
            ->where('is_main', true)
            ->with('supplier')
            ->get()
            ->filter(fn (ProductSupplier $link) => $link->supplier !== null)
            ->mapWithKeys(fn (ProductSupplier $link) => [$link->product_id => [
                'supplier_id' => $link->supplier_id,
                'name' => $link->supplier->name,
                'bag_kg' => $link->bag_kg ?: $link->supplier->default_bag_kg ?: null,
                'carton_kg' => $link->carton_kg ?: $link->supplier->default_carton_kg ?: null,
                'carton_price' => $link->carton_price !== null ? (float) $link->carton_price : null,
            ]])
            ->all();
    }

    public function flush(): void
    {
        $this->main = null;
    }
}
