<?php

namespace Modules\Panneaux\Support;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Panneaux\Models\Panel;
use Modules\Support\Modules;

/** Avertissements d'un panneau : bonbons sans marque, sans type, DLUO courte ou dépassée (seuil : 2 mois). */
class PanelWarnings
{
    public const DLUO_MONTHS = 2;

    /**
     * @return array{missingMark: array<int, string>, missingType: array<int, string>, expiring: array<int, array{name: string, dluo: string, months: int}>, count: int}
     */
    public static function for(Panel $panel): array
    {
        $presence = DB::table('panel_products')->where('panel_id', $panel->id)->get()->keyBy('product_id');
        $with = array_filter(['brand', Modules::enabled('Types') ? 'candyType' : null]);
        $products = Product::whereIn('id', $presence->keys())->with($with)->get();

        $name = fn (Product $p) => (string) $p->translateAttribute('name');
        $showDluo = $panel->boardSettings()['showDluo'];

        $expiring = [];
        if ($showDluo) {
            foreach ($products as $product) {
                $months = PanelItems::monthsUntil($presence[$product->id]->dluo);
                if ($months !== null && $months <= self::DLUO_MONTHS) {
                    $expiring[] = ['name' => $name($product), 'dluo' => $presence[$product->id]->dluo, 'months' => $months];
                }
            }
            usort($expiring, fn ($a, $b) => $a['months'] <=> $b['months']);
        }

        $missingMark = $products->filter(fn (Product $p) => ! $p->brand_id)->map($name)->values()->all();
        $missingType = Modules::enabled('Types') ? $products->filter(fn (Product $p) => $p->candyType->isEmpty())->map($name)->values()->all() : [];

        return [
            'missingMark' => $missingMark,
            'missingType' => $missingType,
            'expiring' => $expiring,
            'count' => count($missingMark) + count($missingType) + count($expiring),
        ];
    }
}
