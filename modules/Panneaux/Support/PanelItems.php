<?php

namespace Modules\Panneaux\Support;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Panneaux\Models\Block;
use Modules\Panneaux\Models\Panel;

/**
 * Bonbons d'un panneau : présence, actif (= en stock), DLUO « MM/AAAA », et appartenance à un bloc manuel.
 * Un bonbon peut figurer sur plusieurs panneaux, avec un statut et une DLUO propres à chacun.
 */
class PanelItems
{
    public static function add(Panel $panel, Product|int $product, ?string $dluo = null): void
    {
        $id = $product instanceof Product ? $product->id : $product;

        DB::table('panel_products')->updateOrInsert(
            ['panel_id' => $panel->id, 'product_id' => $id],
            ['active' => true, 'dluo' => self::normalizeDluo($dluo), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** Retire le bonbon du panneau, y compris de ses blocs manuels. */
    public static function remove(Panel $panel, int $productId): void
    {
        DB::table('panel_products')->where('panel_id', $panel->id)->where('product_id', $productId)->delete();
        DB::table('block_product')->where('product_id', $productId)
            ->whereIn('block_id', $panel->blocks()->pluck('id'))->delete();
    }

    public static function setActive(Panel $panel, int $productId, bool $active): void
    {
        DB::table('panel_products')->where('panel_id', $panel->id)->where('product_id', $productId)
            ->update(['active' => $active, 'updated_at' => now()]);
    }

    /** @return bool false si la valeur n'est pas une DLUO valide (MM/AAAA) */
    public static function setDluo(Panel $panel, int $productId, ?string $dluo): bool
    {
        $normalized = self::normalizeDluo($dluo);

        if (filled($dluo) && $normalized === null) {
            return false;
        }

        DB::table('panel_products')->where('panel_id', $panel->id)->where('product_id', $productId)
            ->update(['dluo' => $normalized, 'updated_at' => now()]);

        return true;
    }

    /** « 3/2021 », « 03-2021 », « 03/21 » → « 03/2021 » ; null si vide ou invalide. */
    public static function normalizeDluo(?string $value): ?string
    {
        $value = trim((string) $value);

        if (! preg_match('~^(\d{1,2})\s*[/.\-]\s*(\d{2}|\d{4})$~', $value, $m) || (int) $m[1] < 1 || (int) $m[1] > 12) {
            return null;
        }

        $year = strlen($m[2]) === 2 ? '20'.$m[2] : $m[2];

        return sprintf('%02d/%s', (int) $m[1], $year);
    }

    /** Clé de tri chronologique d'une DLUO « MM/AAAA ». */
    public static function sortKey(?string $dluo): string
    {
        return preg_match('~^(\d{2})/(\d{4})$~', (string) $dluo, $m) ? $m[2].$m[1] : '999999';
    }

    /** Mois avant la DLUO (négatif si dépassée), ou null. */
    public static function monthsUntil(?string $dluo, ?\DateTimeInterface $now = null): ?int
    {
        if (! preg_match('~^(\d{2})/(\d{4})$~', (string) $dluo, $m)) {
            return null;
        }
        $now ??= now();

        return ((int) $m[2] - (int) $now->format('Y')) * 12 + ((int) $m[1] - (int) $now->format('n'));
    }

    // ---------------------------------------------------------------- blocs manuels

    /** Place un bonbon dans un bloc manuel (à la fin) ; il quitte son éventuel autre bloc manuel du même panneau. */
    public static function assignToBlock(Panel $panel, int $productId, Block $block): void
    {
        DB::table('block_product')->where('product_id', $productId)
            ->whereIn('block_id', $panel->blocks()->pluck('id'))->delete();

        DB::table('block_product')->insert([
            'block_id' => $block->id,
            'product_id' => $productId,
            'slot' => (int) DB::table('block_product')->where('block_id', $block->id)->max('slot') + 1,
        ]);
    }

    public static function renumber(Block $block): void
    {
        DB::table('block_product')->where('block_id', $block->id)->orderBy('slot')->pluck('product_id')
            ->each(fn ($id, $index) => DB::table('block_product')->where('block_id', $block->id)->where('product_id', $id)->update(['slot' => $index + 1]));
    }
}
