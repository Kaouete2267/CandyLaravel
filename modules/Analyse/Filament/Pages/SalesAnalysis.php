<?php

namespace Modules\Analyse\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Analyse\Filament\Widgets\BrandRankingChart;
use Modules\Analyse\Filament\Widgets\BrandShareChart;
use Modules\Analyse\Filament\Widgets\BrandTrendChart;
use Modules\Analyse\Filament\Widgets\MonthlySalesChart;
use Modules\Analyse\Filament\Widgets\SalesOverview;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\Columns;
use Modules\Analyse\Support\Period;
use Modules\Support\RequiresStaffPermission;
use UnitEnum;

/** Ventes d'une période comparées à l'année précédente : chiffres clés, graphiques par marque et classements. */
class SalesAnalysis extends Page implements HasTable
{
    use HasFiltersForm;
    use InteractsWithTable {
        InteractsWithTable::normalizeTableFilterValuesFromQueryString insteadof HasFiltersForm;
    }
    use RequiresStaffPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string $permission = 'confiserie:view-analytics';

    protected static string|UnitEnum|null $navigationGroup = 'Analyse';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Ventes';

    protected static ?string $title = 'Analyse des ventes';

    protected static ?string $slug = 'analyse/ventes';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['md' => 2, 'xl' => 5])->schema([
                Select::make('period')
                    ->label('Période')
                    ->options(Period::PRESETS)
                    ->default('last_12')
                    ->selectablePlaceholder(false),
                DatePicker::make('from')
                    ->label('Du')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
                DatePicker::make('to')
                    ->label('Au')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->visible(fn (Get $get) => $get('period') === 'custom'),
                Select::make('brand')
                    ->label('Marque')
                    ->placeholder('Toutes les marques')
                    ->options(fn () => app(SalesHistory::class)->brands()),
                Select::make('ranking')
                    ->label('Classement')
                    ->options(['products' => 'Par bonbon', 'brands' => 'Par marque', 'suppliers' => 'Par fournisseur'])
                    ->default('products')
                    ->selectablePlaceholder(false),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(['default' => 1, 'lg' => 2])->schema(fn (): array => $this->getWidgetsSchemaComponents([
                SalesOverview::class,
                MonthlySalesChart::class,
                BrandTrendChart::class,
                BrandShareChart::class,
                BrandRankingChart::class,
            ])),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn () => match ($this->ranking()) {
                'brands' => 'Classement des marques',
                'suppliers' => 'Classement des fournisseurs',
                default => 'Classement des bonbons',
            })
            ->description(fn () => Period::fromFilters($this->filters)->label().' — comparé à la même période un an plus tôt')
            ->records(fn (?string $sortColumn, ?string $sortDirection, ?string $search) => $this->rankingRecords($sortColumn, $sortDirection, $search))
            ->paginated(false)
            ->columns([
                TextColumn::make('rank')->label('#')->width('3rem'),
                TextColumn::make('name')
                    ->label(fn () => match ($this->ranking()) {
                        'brands' => 'Marque',
                        'suppliers' => 'Fournisseur principal',
                        default => 'Bonbon',
                    })
                    ->searchable()
                    ->sortable(),
                Columns::sku()->visible(fn () => ! $this->isGroupRanking()),
                TextColumn::make('brand')->label('Marque')->sortable()->visible(fn () => ! $this->isGroupRanking()),
                TextColumn::make('products')->label('Bonbons')->numeric()->sortable()->visible(fn () => $this->isGroupRanking()),
                TextColumn::make('sold')->label('Kg vendus')->numeric()->sortable(),
                TextColumn::make('previous')->label('N-1')->numeric()->sortable()->color('gray'),
                TextColumn::make('evolution')
                    ->label('Évolution')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state === null ? 'nouveau' : sprintf('%+.0f %%', $state))
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'info',
                        $state >= 10 => 'success',
                        $state <= -10 => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?float $state) => match (true) {
                        $state === null => null,
                        $state >= 10 => Heroicon::ArrowTrendingUp,
                        $state <= -10 => Heroicon::ArrowTrendingDown,
                        default => Heroicon::Minus,
                    }),
                TextColumn::make('share')->label('Part')->sortable()->formatStateUsing(fn (float $state) => number_format($state, 1, ',', ' ').' %'),
                TextColumn::make('stockout_days')
                    ->label('Jours en rupture')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray')
                    ->visible(fn () => ! $this->isGroupRanking()),
            ]);
    }

    private function ranking(): string
    {
        return in_array($this->filters['ranking'] ?? null, ['brands', 'suppliers'], true) ? $this->filters['ranking'] : 'products';
    }

    /** Classement par marque ou par fournisseur (une ligne par groupe de bonbons). */
    private function isGroupRanking(): bool
    {
        return $this->ranking() !== 'products';
    }

    /** @return array<string, array<string, mixed>> */
    private function rankingRecords(?string $sortColumn, ?string $sortDirection, ?string $search): array
    {
        $history = app(SalesHistory::class);
        $period = Period::fromFilters($this->filters);
        $previous = $period->previousYear();
        $brand = filled($this->filters['brand'] ?? null) ? (string) $this->filters['brand'] : null;
        $ids = $history->variantIds($brand);

        $sold = $history->sales($period->from, $period->to, $ids);
        $before = $history->sales($previous->from, $previous->to, $ids);
        $stockouts = $this->isGroupRanking() ? [] : $history->stockoutDays($period->from, $period->to);
        $products = $history->products()->only($ids);

        $rows = $this->isGroupRanking()
            ? $products->groupBy($this->ranking() === 'brands' ? 'brand_key' : 'supplier_key')->map(fn ($group, $key) => [
                'key' => "group-{$key}",
                'name' => $group->first()[$this->ranking() === 'brands' ? 'brand' : 'supplier'],
                'products' => $group->count(),
                'sold' => $group->sum(fn (array $p) => $sold[$p['id']] ?? 0),
                'previous' => $group->sum(fn (array $p) => $before[$p['id']] ?? 0),
            ])
            : $products->map(fn (array $p) => [
                'key' => "variant-{$p['id']}",
                'name' => $p['name'],
                'product_id' => $p['product_id'],
                'sku' => $p['sku'],
                'brand' => $p['brand'],
                'sold' => $sold[$p['id']] ?? 0,
                'previous' => $before[$p['id']] ?? 0,
                'stockout_days' => $stockouts[$p['id']] ?? 0,
            ]);

        $total = max(1, $rows->sum('sold'));
        $rows = $rows
            ->map(fn (array $row) => [
                ...$row,
                'share' => $row['sold'] / $total * 100,
                'evolution' => $row['previous'] > 0 ? ($row['sold'] - $row['previous']) / $row['previous'] * 100 : null,
            ])
            ->sortByDesc('sold')->values()
            ->map(fn (array $row, int $i) => [...$row, 'rank' => $i + 1]);

        if (filled($search)) {
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['name'].' '.($row['sku'] ?? '')), mb_strtolower($search)));
        }

        if ($sortColumn) {
            $rows = $rows->sortBy(fn (array $row) => $row[$sortColumn] ?? PHP_INT_MIN, SORT_NATURAL | SORT_FLAG_CASE, $sortDirection === 'desc');
        }

        return $rows->keyBy('key')->all();
    }
}
