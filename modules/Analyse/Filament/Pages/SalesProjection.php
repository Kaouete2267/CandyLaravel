<?php

namespace Modules\Analyse\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Analyse\Filament\Concerns\HasForecastFilters;
use Modules\Analyse\Filament\Widgets\ProjectedSalesChart;
use Modules\Analyse\Filament\Widgets\ProjectedStockChart;
use Modules\Analyse\Filament\Widgets\ProjectionOverview;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\Columns;
use Modules\Support\RequiresStaffPermission;
use UnitEnum;

/**
 * Ventes à venir projetées sur le calendrier de la boutique à partir de l'historique, et stock bonbon par bonbon
 * jusqu'à la prochaine livraison : les ruptures annoncées sont mises en avant.
 */
class SalesProjection extends Page implements HasTable
{
    use HasForecastFilters;
    use InteractsWithTable {
        InteractsWithTable::normalizeTableFilterValuesFromQueryString insteadof HasForecastFilters;
    }
    use RequiresStaffPermission;

    /** @var array<string, array{label: string, color: string, icon: Heroicon}> */
    public const STATUSES = [
        'rupture' => ['label' => 'Rupture avant livraison', 'color' => 'danger', 'icon' => Heroicon::ExclamationTriangle],
        'tension' => ['label' => 'Stock tendu', 'color' => 'warning', 'icon' => Heroicon::ExclamationCircle],
        'ok' => ['label' => 'OK', 'color' => 'success', 'icon' => Heroicon::CheckCircle],
        'no_history' => ['label' => 'Pas d\'historique', 'color' => 'gray', 'icon' => Heroicon::QuestionMarkCircle],
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string $permission = 'confiserie:view-analytics';

    protected static string|UnitEnum|null $navigationGroup = 'Analyse';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Projection';

    protected static ?string $title = 'Projection des ventes et ruptures';

    protected static ?string $slug = 'analyse/projection';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['md' => 2, 'xl' => 4])->schema([
                ...$this->forecastFields(),
                Select::make('horizon')
                    ->label('Horizon')
                    ->options([3 => '3 mois', 6 => '6 mois', 12 => '12 mois'])
                    ->default(6)
                    ->selectablePlaceholder(false),
                Toggle::make('only_risks')
                    ->label('Ruptures et stocks tendus seulement')
                    ->inline(false),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(['default' => 1, 'lg' => 2])->schema(fn (): array => $this->getWidgetsSchemaComponents([
                ProjectionOverview::class,
                ProjectedSalesChart::class,
                ProjectedStockChart::class,
            ])),
            EmbeddedTable::make(),
        ]);
    }

    /** Dernier jour projeté selon l'horizon choisi. */
    public static function horizonEnd(?array $filters, CarbonImmutable $today): CarbonImmutable
    {
        return $today->addMonths(in_array((int) ($filters['horizon'] ?? 6), [3, 6, 12], true) ? (int) $filters['horizon'] : 6)->subDay();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Projection par bonbon')
            ->description('Stock actuel consommé au rythme projeté, sans nouvelle commande. « Ruptures N-1 » : jours d\'ouverture passés à vide sur les 12 derniers mois.')
            ->records(fn (?string $sortColumn, ?string $sortDirection, ?string $search) => $this->projectionRecords($sortColumn, $sortDirection, $search))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Bonbon')->description(fn (array $record) => $record['brand'])->searchable()->sortable(),
                Columns::sku(),
                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state]['label'])
                    ->color(fn (string $state) => self::STATUSES[$state]['color'])
                    ->icon(fn (string $state) => self::STATUSES[$state]['icon']),
                TextColumn::make('stock')->label('Stock')->numeric()->sortable()->suffix(' kg'),
                TextColumn::make('rupture_date')
                    ->label('Rupture prévue')
                    ->sortable()
                    ->placeholder('Au-delà de l\'horizon')
                    ->date('d/m/Y')
                    ->color(fn (array $record) => $record['status'] === 'rupture' ? 'danger' : null)
                    ->weight(fn (array $record) => $record['status'] === 'rupture' ? 'bold' : null)
                    ->description(fn (array $record) => $record['cover_days'] !== null ? "{$record['cover_days']} jours d'ouverture" : null),
                TextColumn::make('stock_at_delivery')
                    ->label('Stock à la livraison')
                    ->sortable()
                    ->formatStateUsing(fn (float $state) => $state < 0 ? 'manque '.number_format(-$state, 0, ',', ' ') : number_format($state, 0, ',', ' '))
                    ->color(fn (float $state) => $state < 0 ? 'danger' : null)
                    ->description(fn (array $record) => $record['next_delivery'] ? 'le '.CarbonImmutable::parse($record['next_delivery'])->format('d/m/Y') : null),
                TextColumn::make('per_open_day')->label('Kg / jour ouvert')->sortable()->numeric(2),
                TextColumn::make('projected')->label('Projection')->sortable()->numeric(0)->suffix(' kg'),
                TextColumn::make('last_year')->label('N-1 même période')->sortable()->numeric()->color('gray'),
                TextColumn::make('stockout_days_last_year')
                    ->label('Ruptures N-1')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn (int $state) => $state > 0 ? "{$state} j" : '—')
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
            ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function projectionRecords(?string $sortColumn, ?string $sortDirection, ?string $search): array
    {
        $today = CarbonImmutable::today();
        $brand = filled($this->filters['brand'] ?? null) ? (string) $this->filters['brand'] : null;
        $ids = array_flip(app(SalesHistory::class)->variantIds($brand));
        $order = array_flip(array_keys(self::STATUSES));

        $rows = collect(app(DemandForecast::class)->projection($this->forecastSettings(), $today, self::horizonEnd($this->filters, $today)))
            ->filter(fn (array $row) => isset($ids[$row['id']]))
            ->when($this->filters['only_risks'] ?? false, fn ($rows) => $rows->whereIn('status', ['rupture', 'tension']))
            ->when(filled($search), fn ($rows) => $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['brand'].' '.$row['sku']), mb_strtolower($search))));

        $rows = $sortColumn
            ? $rows->sortBy(fn (array $row) => $sortColumn === 'status' ? $order[$row['status']] : ($row[$sortColumn] ?? ''), SORT_NATURAL | SORT_FLAG_CASE, $sortDirection === 'desc')
            : $rows->sortBy(fn (array $row) => [$order[$row['status']], $row['rupture_date'] ?? '9999', mb_strtolower($row['name'])]);

        return $rows->all();
    }
}
