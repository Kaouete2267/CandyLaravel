<?php

namespace Modules\Analyse\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\ProductVariant;
use Modules\Fournisseurs\Services\SupplierCatalog;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\StockService;
use Modules\Support\Modules;

/**
 * Historique des ventes lu dans le journal de stock (mouvements « Sortie / vente »), agrégé par bonbon et par jour.
 * Chargé une fois par requête (service singleton) : les pages d'analyse et leurs graphiques le partagent.
 */
class SalesHistory
{
    public const NO_BRAND = 'none';

    public const NO_SUPPLIER = 'none';

    /** @var Collection<int, array{id: int, name: string, sku: ?string, brand: string, product_id: int, brand_key: string, supplier: string, supplier_key: string, stock: int, per_bag: int, per_carton: int}>|null */
    private ?Collection $products = null;

    /** @var array<int, array<string, int>>|null kg vendus [variant][Y-m-d] */
    private ?array $daily = null;

    /** @var array<string, int>|null kg vendus par toute la boutique [Y-m-d] */
    private ?array $shopDaily = null;

    /**
     * Bonbons suivis, indexés par variant.
     *
     * @return Collection<int, array{id: int, name: string, sku: ?string, brand: string, product_id: int, brand_key: string, supplier: string, supplier_key: string, stock: int, per_bag: int, per_carton: int}>
     */
    public function products(): Collection
    {
        $suppliers = Modules::enabled('Fournisseurs') ? app(SupplierCatalog::class) : null;

        return $this->products ??= ProductVariant::with('product.brand')->get()
            ->filter(fn (ProductVariant $variant) => $variant->product !== null)
            ->mapWithKeys(fn (ProductVariant $variant) => [$variant->id => [
                'id' => $variant->id,
                'product_id' => (int) $variant->product_id,
                'name' => (string) $variant->product->translateAttribute('name'),
                'sku' => $variant->sku,
                'brand' => $variant->product->brand?->name ?? 'Sans marque',
                'brand_key' => (string) ($variant->product->brand_id ?? self::NO_BRAND),
                'supplier' => $suppliers?->main($variant->product_id)['name'] ?? 'Sans fournisseur',
                'supplier_key' => (string) ($suppliers?->main($variant->product_id)['supplier_id'] ?? self::NO_SUPPLIER),
                'stock' => (int) $variant->stock,
                'per_bag' => StockService::kgPerBag($variant),
                'per_carton' => StockService::kgPerCarton($variant),
            ]])
            ->sortBy(fn (array $product) => mb_strtolower($product['name']));
    }

    /**
     * Marques présentes dans le catalogue (clé => nom), « Sans marque » en dernier.
     *
     * @return array<string, string>
     */
    public function brands(): array
    {
        return $this->products()
            ->mapWithKeys(fn (array $product) => [$product['brand_key'] => $product['brand']])
            ->sortBy(fn (string $name, int|string $key) => [(string) $key === self::NO_BRAND, mb_strtolower($name)])
            ->all();
    }

    /**
     * Variants d'une marque (toutes si null).
     *
     * @return list<int>
     */
    public function variantIds(?string $brandKey = null): array
    {
        return $this->products()
            ->when(filled($brandKey), fn (Collection $products) => $products->where('brand_key', $brandKey))
            ->keys()->all();
    }

    /** @return array<int, array<string, int>> */
    public function daily(): array
    {
        if ($this->daily !== null) {
            return $this->daily;
        }

        $this->daily = [];
        StockMovement::query()
            ->where('reason', StockReason::Sale)
            ->selectRaw('product_variant_id, DATE(created_at) as day, -SUM(quantity) as kg')
            ->groupBy('product_variant_id', DB::raw('DATE(created_at)'))
            ->toBase()->get()
            ->each(function (object $row) {
                $this->daily[(int) $row->product_variant_id][(string) $row->day] = (int) $row->kg;
            });

        return $this->daily;
    }

    /** @return array<string, int> */
    public function shopDaily(): array
    {
        if ($this->shopDaily !== null) {
            return $this->shopDaily;
        }

        $this->shopDaily = [];
        foreach ($this->daily() as $days) {
            foreach ($days as $day => $kg) {
                $this->shopDaily[$day] = ($this->shopDaily[$day] ?? 0) + $kg;
            }
        }
        ksort($this->shopDaily);

        return $this->shopDaily;
    }

    /**
     * Kg vendus par bonbon entre deux dates incluses.
     *
     * @param  list<int>|null  $variantIds
     * @return array<int, int>
     */
    public function sales(CarbonImmutable $from, CarbonImmutable $to, ?array $variantIds = null): array
    {
        [$first, $last] = [$from->toDateString(), $to->toDateString()];
        $totals = [];

        foreach ($variantIds ?? array_keys($this->daily()) as $variantId) {
            $sum = 0;
            foreach ($this->daily()[$variantId] ?? [] as $day => $kg) {
                if ($day >= $first && $day <= $last) {
                    $sum += $kg;
                }
            }
            $totals[$variantId] = $sum;
        }

        return $totals;
    }

    /**
     * Kg vendus par mois (Y-m), mois vides compris.
     *
     * @param  list<int>|null  $variantIds
     * @return array<string, int>
     */
    public function monthly(CarbonImmutable $from, CarbonImmutable $to, ?array $variantIds = null): array
    {
        $months = [];
        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonth()) {
            $months[$month->format('Y-m')] = 0;
        }

        [$first, $last] = [$from->toDateString(), $to->toDateString()];
        foreach ($variantIds ?? array_keys($this->daily()) as $variantId) {
            foreach ($this->daily()[$variantId] ?? [] as $day => $kg) {
                if ($day >= $first && $day <= $last) {
                    $months[substr($day, 0, 7)] += $kg;
                }
            }
        }

        return $months;
    }

    /**
     * Kg vendus par marque (clé => kg) entre deux dates incluses, du plus vendu au moins vendu.
     *
     * @param  list<int>|null  $variantIds
     * @return array<string, int>
     */
    public function byBrand(CarbonImmutable $from, CarbonImmutable $to, ?array $variantIds = null): array
    {
        $byBrand = [];
        foreach ($this->sales($from, $to, $variantIds ?? $this->variantIds()) as $id => $kg) {
            $brandKey = $this->products()[$id]['brand_key'] ?? self::NO_BRAND;
            $byBrand[$brandKey] = ($byBrand[$brandKey] ?? 0) + $kg;
        }

        arsort($byBrand);

        return $byBrand;
    }

    /** Jours où la boutique a vendu quelque chose entre deux dates incluses (= jours d'ouverture constatés). */
    public function openDays(CarbonImmutable $from, CarbonImmutable $to): int
    {
        [$first, $last] = [$from->toDateString(), $to->toDateString()];

        return collect($this->shopDaily())->filter(fn (int $kg, string $day) => $day >= $first && $day <= $last && $kg > 0)->count();
    }

    /**
     * Jours d'ouverture passés en rupture (stock à 0 du début à la fin de la journée) par bonbon, entre deux dates incluses.
     *
     * @return array<int, int>
     */
    public function stockoutDays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $endOfDay = [];
        StockMovement::query()
            ->where('created_at', '<=', $to->endOfDay())
            ->orderBy('created_at')->orderBy('id')
            ->toBase()->get(['product_variant_id', 'stock_after', 'created_at'])
            ->each(function (object $row) use (&$endOfDay) {
                $endOfDay[(int) $row->product_variant_id][substr((string) $row->created_at, 0, 10)] = (int) $row->stock_after;
            });

        $openDays = array_keys(array_filter($this->shopDaily(), fn (int $kg, string $day) => $kg > 0 && $day >= $from->toDateString() && $day <= $to->toDateString(), ARRAY_FILTER_USE_BOTH));
        $result = [];

        foreach ($endOfDay as $variantId => $days) {
            $count = 0;
            $stock = null;
            $dates = array_keys($days);
            $cursor = 0;

            foreach ($openDays as $openDay) {
                // Stock au début de la journée = stock en fin du dernier jour avec mouvement avant celle-ci.
                while ($cursor < count($dates) && $dates[$cursor] < $openDay) {
                    $stock = $days[$dates[$cursor]];
                    $cursor++;
                }

                // Rupture : vide au début de la journée et pas de livraison dans la journée.
                if ($stock === 0 && ($days[$openDay] ?? 0) === 0) {
                    $count++;
                }
            }

            $result[$variantId] = $count;
        }

        return $result;
    }
}
