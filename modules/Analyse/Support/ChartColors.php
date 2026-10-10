<?php

namespace Modules\Analyse\Support;

use Carbon\CarbonImmutable;
use Modules\Analyse\Services\SalesHistory;

/**
 * Couleurs des graphiques. Palette catégorielle validée (lisible par les daltoniens) prise dans un ordre fixe :
 * chaque marque garde sa couleur quel que soit le filtre (attribuée selon son rang sur tout l'historique) ;
 * au-delà de 7 marques, les suivantes sont regroupées en « Autres » (gris).
 */
final class ChartColors
{
    /** @var list<string> */
    public const CATEGORICAL = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7'];

    public const PRIMARY = '#2a78d6';

    /** Série de comparaison (N-1, scénario sans livraison). */
    public const MUTED = '#9b9a95';

    public const OTHERS = '#c3c2bc';

    public const OTHERS_LABEL = 'Autres marques';

    public function __construct(private SalesHistory $history) {}

    /**
     * Couleur de chaque marque (clé => hex) ; les marques hors des 7 premières n'y figurent pas.
     *
     * @return array<string, string>
     */
    public function brands(): array
    {
        $sales = $this->history->sales(CarbonImmutable::create(2000), CarbonImmutable::today());
        $byBrand = [];

        foreach ($this->history->products() as $id => $product) {
            $byBrand[$product['brand_key']] = ($byBrand[$product['brand_key']] ?? 0) + ($sales[$id] ?? 0);
        }

        arsort($byBrand);

        return collect(array_keys($byBrand))
            ->take(count(self::CATEGORICAL))
            ->values()
            ->mapWithKeys(fn (int|string $brandKey, int $slot) => [$brandKey => self::CATEGORICAL[$slot]])
            ->all();
    }

    /** Ajoute une transparence à une couleur hex (#rrggbb). */
    public static function alpha(string $hex, float $alpha): string
    {
        return $hex.str_pad(dechex((int) round($alpha * 255)), 2, '0', STR_PAD_LEFT);
    }
}
