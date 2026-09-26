<?php

namespace Modules\Vitrine\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Base\FieldType;
use Lunar\Models\Product;
use Modules\Allergenes\Support\ProductAllergens;
use Modules\Support\Modules;

/**
 * Filtres du catalogue public, lus depuis l'URL (?q=…&brands[]=…&sans[]=…).
 * La recherche texte se fait en PHP (insensible aux accents et à la langue) : le catalogue d'une
 * confiserie tient largement en mémoire, et le JSON traduit de Lunar se prête mal au LIKE.
 */
class CatalogueQuery
{
    public const SORTS = ['name', 'brand'];

    /**
     * @param  array<int, int>  $brands
     * @param  array<int, int>  $without  allergènes à éviter
     */
    public function __construct(
        public string $q = '',
        public array $brands = [],
        public ?int $panel = null,
        public array $without = [],
        public bool $traces = true,
        public string $sort = 'name',
        public array $types = [],
    ) {}

    public static function fromRequest(Request $request): static
    {
        $ints = fn (string $key) => collect((array) $request->query($key, []))->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();

        return new static(
            q: Str::limit(trim((string) $request->query('q', '')), 80, ''),
            brands: $ints('brands'),
            panel: (int) $request->query('panel') ?: null,
            without: $ints('sans'),
            traces: $request->query('traces', '1') !== '0',
            sort: in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'name',
            types: $ints('types'),
        );
    }

    public function isFiltered(): bool
    {
        return $this->q !== '' || $this->brands || $this->panel || $this->without || $this->types;
    }

    /** @return Collection<int, Product> */
    public function products(): Collection
    {
        $with = ['brand', 'thumbnail'];
        Modules::enabled('Allergenes') && $with[] = 'allergens';
        Modules::enabled('Panneaux') && $with[] = 'panels';
        Modules::enabled('Types') && $with[] = 'candyType';

        $query = Product::query()->where('status', 'published')->with($with);

        if ($this->brands) {
            $query->whereIn('brand_id', $this->brands);
        }

        if ($this->types && Modules::enabled('Types')) {
            $query->whereHas('candyType', fn ($q) => $q->whereIn('candy_types.id', $this->types));
        }

        // Filtre par panneau : les bonbons actifs (en stock) sur ce panneau.
        if ($this->panel && Modules::enabled('Panneaux')) {
            $query->whereHas('panels', fn ($q) => $q->where('panels.id', $this->panel)->where('panel_products.active', true));
        }

        if (Modules::enabled('Allergenes')) {
            ProductAllergens::excluding($query, $this->without, $this->traces);
        }

        $products = $query->get();

        if ($this->q !== '') {
            $needle = self::normalize($this->q);
            $products = $products->filter(fn (Product $p) => str_contains(self::haystack($p), $needle));
        }

        return $this->sorted($products)->values();
    }

    private function sorted(Collection $products): Collection
    {
        $name = fn (Product $p) => self::normalize((string) $p->translateAttribute('name'));

        return match ($this->sort) {
            'brand' => $products->sortBy([fn ($a, $b) => self::normalize((string) $a->brand?->name) <=> self::normalize((string) $b->brand?->name), fn ($a, $b) => $name($a) <=> $name($b)]),
            default => $products->sortBy($name),
        };
    }

    public static function normalize(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }

    /** Nom et description dans toutes les langues + marque, normalisés. */
    private static function haystack(Product $product): string
    {
        $parts = [$product->brand?->name];

        foreach (['name', 'description'] as $handle) {
            $field = $product->attribute_data?->get($handle);
            $value = $field?->getValue();

            foreach (is_iterable($value) ? $value : [$value] as $translation) {
                $parts[] = $translation instanceof FieldType ? $translation->getValue() : $translation;
            }
        }

        return self::normalize(implode(' ', array_filter($parts, 'is_string')));
    }
}
