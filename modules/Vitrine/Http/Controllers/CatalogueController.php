<?php

namespace Modules\Vitrine\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Modules\Allergenes\Enums\AllergenLevel;
use Modules\Allergenes\Models\Allergen;
use Modules\Panneaux\Models\Panel;
use Modules\Support\Modules;
use Modules\Types\Models\CandyType;
use Modules\Vitrine\Support\CatalogueQuery;

class CatalogueController extends Controller
{
    public function index(Request $request)
    {
        $filters = CatalogueQuery::fromRequest($request);

        return view('vitrine::catalogue', [
            'filters' => $filters,
            'products' => $filters->products(),
            ...self::filterOptions($filters),
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->status === 'published', 404);

        $product->load(['brand', 'thumbnail', 'media']);
        Modules::enabled('Allergenes') && $product->load('allergens');
        Modules::enabled('Panneaux') && $product->load('panels');
        Modules::enabled('Types') && $product->load('candyType');

        return view('vitrine::product', [
            'product' => $product,
            'contains' => Modules::enabled('Allergenes') ? $product->allergens->filter(fn ($a) => $a->pivot->type === AllergenLevel::Contains->value) : collect(),
            'traces' => Modules::enabled('Allergenes') ? $product->allergens->filter(fn ($a) => $a->pivot->type === AllergenLevel::MayContain->value) : collect(),
        ]);
    }

    /** Listes des filtres (marques, panneaux, types, allergènes) partagées avec la page de recherche par photo. */
    public static function filterOptions(CatalogueQuery $filters): array
    {
        $panels = Modules::enabled('Panneaux') ? Panel::orderBy('name')->get() : collect();

        return [
            'brands' => Brand::whereHas('products', fn ($q) => $q->where('status', 'published'))->orderBy('name')->get(),
            'panels' => $panels,
            'allergens' => Modules::enabled('Allergenes') ? Allergen::orderBy('position')->get() : collect(),
            'types' => Modules::enabled('Types') ? CandyType::orderBy('position')->get() : collect(),
        ];
    }
}
