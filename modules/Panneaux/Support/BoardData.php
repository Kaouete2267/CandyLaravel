<?php

namespace Modules\Panneaux\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Modules\Allergenes\Support\AllergenTerms;
use Modules\Panneaux\Models\Block;
use Modules\Panneaux\Models\Panel;
use Modules\Support\HtmlSanitizer;
use Modules\Support\Modules;
use Modules\Types\Models\CandyType;

/**
 * Prépare tout ce qu'il faut pour dessiner (et imprimer) un panneau :
 *  - les blocs d'étiquettes (marque = alimenté automatiquement, manuel = bonbons choisis) ;
 *  - les blocs d'information (système ou personnalisés), déjà rendus avec le style du panneau ;
 *  - le récapitulatif des DLUO, les bonbons qu'aucun bloc n'affiche.
 * Un bonbon s'imprime s'il est publié ET actif (= en stock) sur ce panneau.
 */
class BoardData
{
    /** @var array<int, ?string> */
    private static array $logos = [];

    /**
     * @return array{settings: array<string, mixed>, labelBlocks: array<int, array<string, mixed>>, before: array<int, array<string, mixed>>, after: array<int, array<string, mixed>>, recap: array<int, array<string, mixed>>, total: int, orphans: array<int, array<string, mixed>>}
     */
    public static function for(Panel $panel): array
    {
        $settings = $panel->boardSettings();
        $blocks = $panel->blocks()->with(['brand', 'products'])->get();

        /** @var Collection<int, object> $presence product_id => {active, dluo} */
        $presence = DB::table('panel_products')->where('panel_id', $panel->id)->get()->keyBy('product_id');

        $with = array_filter(['brand', Modules::enabled('Types') ? 'candyType' : null]);
        $products = Product::whereIn('id', $presence->keys())->where('status', 'published')->with($with)->get()
            ->filter(fn (Product $p) => (bool) $presence[$p->id]->active)
            ->keyBy('id');

        // Bonbons choisis à la main : ils quittent les blocs « marque » automatiques.
        $manualIds = $blocks->filter(fn (Block $b) => $b->kind === Block::KIND_MANUAL && $b->active)
            ->flatMap(fn (Block $b) => $b->products->pluck('id'))->unique()->all();

        $shown = [];
        $labelBlocks = [];

        foreach ($blocks->where('zone', 'labels') as $block) {
            /** @var Block $block */
            $members = $block->kind === Block::KIND_MANUAL
                ? $block->products->filter(fn ($p) => $products->has($p->id))->map(fn ($p) => $products[$p->id])
                : $products->filter(fn (Product $p) => $p->brand_id == $block->brand_id && ! in_array($p->id, $manualIds, true))
                    ->sortBy(fn (Product $p) => Str::lower(Str::ascii((string) $p->translateAttribute('name'))));

            $labels = $members->values()->map(fn (Product $p) => self::label($p, $presence[$p->id]->dluo))->all();

            if ($block->active) {
                array_push($shown, ...array_column($labels, 'id'));
            }

            $labelBlocks[] = [
                'id' => $block->id,
                'code' => $block->code,
                'kind' => $block->kind,
                'active' => $block->active,
                'title' => $block->displayTitle(),
                'logo' => $block->brand ? self::logo($block->brand) : null,
                'showHeader' => $block->show_header,
                'count' => count($labels),
                'labels' => $labels,
            ];
        }

        // Statistiques pour les blocs d'information : marques et types des bonbons imprimés.
        $printed = $products->values();
        $brandStats = self::brandStats($printed);
        $types = Modules::enabled('Types') ? CandyType::orderBy('position')->get() : collect();

        $info = ['before' => [], 'after' => []];
        foreach ($blocks->where('zone', '!=', 'labels')->where('active', true) as $block) {
            /** @var Block $block */
            $info[$block->zone][] = [
                'id' => $block->id,
                'code' => $block->code,
                'kind' => $block->kind,
                'width' => max(1, min(Block::GRID_COLUMNS, $block->width)),
                'repeat' => $block->repeat,
                'title' => $block->displayTitle(),
                'system' => $block->isSystem(),
                'html' => self::renderInfo($block, $settings, $brandStats, $printed->count(), $types),
            ];
        }

        $recap = [];
        if ($settings['showDluo'] && $settings['page']['dluo']) {
            $recap = $products->filter(fn (Product $p) => filled($presence[$p->id]->dluo))
                ->groupBy(fn (Product $p) => $presence[$p->id]->dluo)
                ->map(fn ($group, $dluo) => ['dluo' => $dluo, 'names' => $group->map(fn (Product $p) => (string) $p->translateAttribute('name'))->sort()->values()->all()])
                ->sortBy(fn ($row) => PanelItems::sortKey($row['dluo']))
                ->values()->all();
        }

        $orphans = $products->reject(fn (Product $p) => in_array($p->id, $shown, true))
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => (string) $p->translateAttribute('name'), 'brand' => $p->brand?->name, 'brand_id' => $p->brand_id])
            ->values()->all();

        return [
            'settings' => $settings,
            'labelBlocks' => $labelBlocks,
            'before' => $info['before'],
            'after' => $info['after'],
            'recap' => $recap,
            'total' => count($shown),
            'orphans' => $orphans,
        ];
    }

    /**
     * Étiquette d'un bonbon. Les allergènes surlignés dans les ingrédients viennent du module Allergènes
     * (mêmes mots-clés que la détection automatique à l'import) plutôt que d'une liste par panneau :
     * une seule source à tenir à jour, partagée par tout le catalogue.
     *
     * @return array<string, mixed>
     */
    private static function label(Product $product, ?string $dluo): array
    {
        $type = Modules::enabled('Types') ? $product->candyType->first() : null;
        $ingredients = (string) $product->translateAttribute('ingredients');
        $highlighted = $ingredients === '' ? '' : (Modules::enabled('Allergenes') ? AllergenTerms::highlight($ingredients) : e($ingredients));

        return [
            'id' => $product->id,
            'name' => (string) $product->translateAttribute('name'),
            'brand' => $product->brand?->name,
            'logo' => $product->brand ? self::logo($product->brand) : null,
            'type' => $type?->name,
            'color' => $type?->color,
            'ink' => $type ? ($type->font_color ?: '#000000') : null,
            'ingredients' => $highlighted,
            'dluo' => $dluo,
        ];
    }

    /** @return array<int, array{brand: ?Brand, count: int}> */
    private static function brandStats(Collection $printed): array
    {
        $counts = $printed->groupBy(fn (Product $p) => $p->brand_id ?? 0)->map->count();
        $brands = Brand::whereIn('id', $counts->keys()->filter())->orderBy('name')->get()->keyBy('id');

        $rows = [];
        foreach ($brands as $brand) {
            $rows[] = ['brand' => $brand, 'count' => $counts[$brand->id]];
        }
        if ($counts->has(0)) {
            $rows[] = ['brand' => null, 'count' => $counts[0]];
        }

        return $rows;
    }

    /** HTML d'un bloc d'information, habillé avec le style « zone d'information » du panneau. */
    private static function renderInfo(Block $block, array $settings, array $brandStats, int $total, Collection $types): string
    {
        $inner = match ($block->kind) {
            Block::KIND_MARK => self::markTiles($brandStats, $total, (bool) $block->option('showPercent', true)),
            Block::KIND_LEGEND => $types->map(fn (CandyType $t) => '<span class="etq-info-legende"><i style="background:'.e($t->color ?: '#e5e7eb').'"></i>'.e($t->name).'</span>')->implode(''),
            default => HtmlSanitizer::clean($block->content),
        };

        if ($inner === '' || ($block->kind === Block::KIND_MARK && $total === 0)) {
            return '';
        }

        $info = $settings['info'];
        $extra = $block->kind === Block::KIND_MARK ? ' etq-info-marque' : '';

        return '<div class="etq etq-info'.$extra.'" style="border:1px solid '.e($info['bordercolor']).'">'
            .'<div class="title" style="background-color:'.e($info['bgcolor']['head']).';'.self::font($info['title']).'">'.e($block->displayTitle()).'</div>'
            .'<div class="corp" style="background-color:'.e($info['bgcolor']['body']).';'.self::font($info['content']).'">'.$inner.'</div></div>';
    }

    private static function markTiles(array $stats, int $total, bool $showPercent): string
    {
        $html = '';
        foreach ($stats as $row) {
            $pct = $total > 0 ? round($row['count'] * 100 / $total, 1) : 0;
            $count = $showPercent ? $row['count'].' ('.rtrim(rtrim(number_format($pct, 1, ',', ''), '0'), ',').' %)' : (string) $row['count'];
            $logo = $row['brand'] ? self::logo($row['brand']) : null;
            $label = $logo ? '<img src="'.e($logo).'" alt="'.e($row['brand']->name).'">' : e(mb_strtoupper($row['brand']?->name ?? 'SANS MARQUE'));
            $html .= '<span class="etq-info-marque-tile">'.$label.'<b>'.$count.'</b></span>';
        }

        return $html;
    }

    /** @param array{0: string, 1: string, 2: string} $font */
    public static function font(array $font): string
    {
        return "font-family:'".e($font[0])."';font-size:".(int) $font[1].'px;color:'.e($font[2]).';';
    }

    private static function logo(Brand $brand): ?string
    {
        return self::$logos[$brand->id] ??= ($brand->getFirstMediaUrl('images', 'small') ?: $brand->getFirstMediaUrl('images') ?: null);
    }
}
