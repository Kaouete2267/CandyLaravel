<?php

namespace Modules\Fournisseurs\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Jeu de départ des fournisseurs, livré avec l'application : 3 fournisseurs fictifs et une répartition
 * reproductible des bonbons (1 ou 2 fournisseurs chacun, un principal ; quelques-uns sans fournisseur pour
 * figurer un oubli de saisie), avec prix d'achat et conditionnements (sac de vente, carton d'achat : un nombre entier de sacs).
 */
class SuppliersDataset
{
    public const SEED = 2026;

    /** Part des bonbons laissés sans fournisseur. */
    public const WITHOUT_SUPPLIER = 0.08;

    /** Part des bonbons proposés par deux fournisseurs. */
    public const TWO_SUPPLIERS = 0.3;

    /** @var list<array<string, mixed>> */
    public const SUPPLIERS = [
        [
            'code' => 'DLR',
            'name' => 'Confiserie Delarue',
            'contact_name' => 'Claire Delarue',
            'email' => 'commandes@confiserie-delarue.example',
            'phone' => '03 20 00 00 01',
            'address' => "12 rue des Sucres\n59000 Lille",
            'default_bag_kg' => 1,
            'default_carton_kg' => 3,
            'lead_time_days' => 5,
            'settings' => ['Franco de port' => '250 € HT', 'Jour de commande' => 'Lundi', 'Paiement' => '30 jours fin de mois'],
        ],
        [
            'code' => 'SDN',
            'name' => 'Sucreries du Nord',
            'contact_name' => 'Marc Vandamme',
            'email' => 'pro@sucreries-du-nord.example',
            'phone' => '03 28 00 00 02',
            'address' => "Zone artisanale des Prés\n59140 Dunkerque",
            'default_bag_kg' => 1,
            'default_carton_kg' => 4,
            'lead_time_days' => 10,
            'settings' => ['Franco de port' => '400 € HT', 'Minimum de commande' => '20 cartons', 'Paiement' => '45 jours'],
        ],
        [
            'code' => 'BCD',
            'name' => 'Bonbons & Co Distribution',
            'contact_name' => 'Service clients',
            'email' => 'service@bonbons-co.example',
            'phone' => '01 40 00 00 03',
            'address' => "8 avenue du Commerce\n93200 Saint-Denis",
            'default_bag_kg' => 2,
            'default_carton_kg' => 6,
            'lead_time_days' => 7,
            'settings' => ['Franco de port' => '300 € HT', 'Remise volume' => '3 % dès 50 cartons'],
        ],
    ];

    /** @return array{suppliers: int, links: int, without_supplier: int} */
    public function seed(int $seed = self::SEED): array
    {
        $random = new Randomizer(new Mt19937($seed));

        return DB::transaction(function () use ($random) {
            $suppliers = collect(self::SUPPLIERS)->map(fn (array $data) => [
                'code' => $data['code'],
                'model' => Supplier::create(collect($data)->except('code')->all()),
            ])->values();

            $links = 0;
            $without = 0;

            foreach (Product::query()->orderBy('id')->pluck('id') as $productId) {
                if ($random->getFloat(0, 1) < self::WITHOUT_SUPPLIER) {
                    $without++;

                    continue;
                }

                $count = $random->getFloat(0, 1) < self::TWO_SUPPLIERS ? 2 : 1;
                $pricePerKg = round($random->getFloat(6, 14), 2);

                foreach (array_slice($random->shuffleArray($suppliers->keys()->all()), 0, $count) as $i => $key) {
                    ['code' => $code, 'model' => $supplier] = $suppliers[$key];
                    // 30 % des bonbons ont un conditionnement propre (sac et carton), sinon celui du fournisseur.
                    [$bagKg, $cartonKg] = [null, null];
                    if ($random->getFloat(0, 1) >= 0.7) {
                        $bagKg = [1, 2][$random->getInt(0, 1)];
                        $cartonKg = $bagKg * [2, 3, 4][$random->getInt(0, 2)];
                    }
                    $kg = $cartonKg ?? $supplier->default_carton_kg;

                    ProductSupplier::create([
                        'product_id' => $productId,
                        'supplier_id' => $supplier->id,
                        'reference' => $code.'-'.str_pad((string) $random->getInt(1, 9999), 4, '0', STR_PAD_LEFT),
                        'bag_kg' => $bagKg,
                        'carton_kg' => $cartonKg,
                        'carton_price' => round($kg * $pricePerKg * $random->getFloat(0.92, 1.08), 2),
                        'is_main' => $i === 0,
                    ]);
                    $links++;
                }
            }

            return ['suppliers' => $suppliers->count(), 'links' => $links, 'without_supplier' => $without];
        });
    }

    /** Supprime tous les fournisseurs (et leurs liens avec les bonbons). */
    public function clear(): void
    {
        Supplier::query()->delete();
        app(SupplierCatalog::class)->flush();
    }
}
