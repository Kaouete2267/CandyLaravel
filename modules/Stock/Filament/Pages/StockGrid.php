<?php

namespace Modules\Stock\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Panneaux\Models\Panel;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\InsufficientStock;
use Modules\Stock\Services\StockService;
use Modules\Support\Modules;
use Modules\Support\RequiresStaffPermission;
use UnitEnum;

/**
 * Grille photo des bonbons ; un clic ouvre un overlay pour entrer / sortir du stock
 * en sacs ou en cartons (1 carton = N sacs), ou fixer la valeur après un inventaire.
 */
class StockGrid extends Page
{
    use RequiresStaffPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string $permission = 'confiserie:manage-stock';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Stock';

    protected static ?string $title = 'Gestion du stock';

    protected static ?string $slug = 'stock';

    protected string $view = 'stock::pages.stock-grid';

    // Filtres
    public string $search = '';

    public $panelId = null;

    public string $status = 'all'; // all | low | out

    // Overlay
    public $variantId = null;

    public string $direction = 'in'; // in | out | set

    public string $unit = StockService::UNIT_CARTON;

    public $quantity = 1;

    public string $reason = 'receipt';

    public string $note = '';

    #[Computed]
    public function cards(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $this->loadCards()
            ->when(filled($this->panelId), fn (Collection $c) => $c->filter(fn (array $card) => in_array((int) $this->panelId, $card['panelIds'], true)))
            ->when($this->status === 'low', fn (Collection $c) => $c->whereIn('level', ['low', 'out']))
            ->when($this->status === 'out', fn (Collection $c) => $c->where('level', 'out'))
            ->when($search !== '', fn (Collection $c) => $c->filter(
                fn (array $card) => str_contains(mb_strtolower("{$card['name']} {$card['brand']} {$card['sku']}"), $search)
            ))
            ->values();
    }

    #[Computed]
    public function counts(): array
    {
        $all = $this->loadCards();

        return [
            'all' => $all->count(),
            'low' => $all->whereIn('level', ['low', 'out'])->count(),
            'out' => $all->where('level', 'out')->count(),
        ];
    }

    #[Computed]
    public function panels(): array
    {
        return Modules::enabled('Panneaux') ? Panel::orderBy('name')->pluck('name', 'id')->all() : [];
    }

    /** Carte du produit ouvert dans l'overlay (indépendante des filtres). */
    #[Computed]
    public function selected(): ?array
    {
        return $this->loadCards()->firstWhere('id', (int) $this->variantId);
    }

    /** @return array<int, StockMovement> */
    #[Computed]
    public function history(): Collection
    {
        return blank($this->variantId) ? collect() : StockMovement::with('staff')
            ->where('product_variant_id', (int) $this->variantId)->latest()->limit(5)->get();
    }

    /** Effet de la saisie en cours : sacs de différence et stock résultant. */
    #[Computed]
    public function preview(): ?array
    {
        $card = $this->selected;

        if (! $card) {
            return null;
        }

        $quantity = max(0, (int) $this->quantity);
        $bags = $this->unit === StockService::UNIT_CARTON ? $quantity * $card['perCarton'] : $quantity;
        $delta = match ($this->direction) {
            'in' => $bags,
            'out' => -$bags,
            default => $bags - $card['stock'],
        };
        $after = $card['stock'] + $delta;
        [$cartons, $loose] = StockService::breakdown($after, $card['perCarton']);

        return compact('delta', 'after', 'cartons', 'loose') + ['valid' => $after >= 0];
    }

    public function openOverlay(int $variantId): void
    {
        $this->variantId = $variantId;
        $this->resetErrorBag();
        $this->setDirection('in');
    }

    public function closeOverlay(): void
    {
        $this->variantId = null;
        $this->resetErrorBag();
    }

    public function setDirection(string $direction): void
    {
        $this->direction = in_array($direction, ['in', 'out', 'set'], true) ? $direction : 'in';
        $this->reason = StockReason::forDirection($this->direction)[0]->value;
        $this->resetErrorBag();

        $card = $this->selected;

        if ($this->direction === 'set') {
            // Inventaire : on compte des sacs, en partant du stock actuel.
            $this->unit = StockService::UNIT_BAG;
            $this->quantity = $card['stock'] ?? 0;
        } else {
            $this->unit = ($card['perCarton'] ?? 1) > 1 ? StockService::UNIT_CARTON : StockService::UNIT_BAG;
            $this->quantity = 1;
        }
    }

    public function setUnit(string $unit): void
    {
        $this->unit = $unit === StockService::UNIT_CARTON ? StockService::UNIT_CARTON : StockService::UNIT_BAG;
    }

    public function adjust(int $step): void
    {
        $this->quantity = max($this->direction === 'set' ? 0 : 1, (int) $this->quantity + $step);
    }

    public function submit(): void
    {
        $card = $this->selected;
        $quantity = (int) $this->quantity;
        $reason = StockReason::tryFrom($this->reason);

        if (! $card || ! $reason || ! in_array($reason, StockReason::forDirection($this->direction), true)) {
            $this->addError('reason', 'Choisissez un motif.');

            return;
        }

        if ($quantity < ($this->direction === 'set' ? 0 : 1)) {
            $this->addError('quantity', 'Indiquez une quantité d\'au moins 1.');

            return;
        }

        $variant = ProductVariant::with('product')->findOrFail($card['id']);
        $delta = $this->preview['delta'];

        if ($delta === 0) {
            Notification::make()->title('Aucun changement de stock')->info()->send();
            $this->closeOverlay();

            return;
        }

        try {
            app(StockService::class)->move(
                $variant, $delta, $reason,
                staffId: auth('staff')->id(),
                inputUnit: $this->unit,
                inputQuantity: $quantity,
                note: filled($this->note) ? trim($this->note) : null,
            );
        } catch (InsufficientStock $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }

        Notification::make()->success()
            ->title('Stock mis à jour')
            ->body(sprintf('%s : %s%d sac(s) → %d en stock', $card['name'], $delta > 0 ? '+' : '−', abs($delta), $variant->stock))
            ->send();

        $this->closeOverlay();
    }

    /** @return Collection<int, array<string, mixed>> une carte par produit (premier variant = le sac) */
    protected function loadCards(): Collection
    {
        $placement = Modules::enabled('Panneaux');

        return Product::with(array_filter(['variants', 'brand', 'thumbnail', $placement ? 'panels' : null]))
            ->get()
            ->filter(fn (Product $p) => $p->variants->isNotEmpty())
            ->map(fn (Product $p) => $this->card($p, $placement))
            ->sortBy([['where', 'asc'], ['name', 'asc']])
            ->values();
    }

    /** @return array<string, mixed> */
    protected function card(Product $product, bool $placement): array
    {
        /** @var ProductVariant $variant */
        $variant = $product->variants->first();
        $variant->setRelation('product', $product);

        $perCarton = StockService::bagsPerCarton($variant);
        $min = StockService::minStock($variant);
        [$cartons, $loose] = StockService::breakdown($variant->stock, $perCarton);
        $panels = $placement ? $product->panels->filter(fn ($p) => $p->pivot->active) : collect();

        return [
            'id' => $variant->id,
            'name' => (string) $product->attr('name'),
            'brand' => (string) $product->brand?->name,
            'sku' => (string) $variant->sku,
            'image' => $product->getThumbnailImage() ?: null,
            'stock' => $variant->stock,
            'perCarton' => $perCarton,
            'min' => $min,
            'weight' => rtrim(rtrim(number_format((float) $variant->weight_value, 3, ',', ''), '0'), ','),
            'cartons' => $cartons,
            'loose' => $loose,
            'level' => $variant->stock <= 0 ? 'out' : ($variant->stock <= $min ? 'low' : 'ok'),
            'panelIds' => $panels->pluck('id')->all(),
            'where' => $panels->isNotEmpty() ? $panels->pluck('name')->implode(' · ') : '~ Hors panneau',
        ];
    }
}
