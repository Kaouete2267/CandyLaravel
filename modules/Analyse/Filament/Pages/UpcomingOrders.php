<?php

namespace Modules\Analyse\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Modules\Analyse\Filament\Concerns\HasForecastFilters;
use Modules\Analyse\Filament\Widgets\OrdersOverview;
use Modules\Analyse\Services\DemandForecast;
use Modules\Analyse\Services\SalesHistory;
use Modules\Analyse\Support\Columns;
use Modules\Fournisseurs\Services\SupplierCatalog;
use Modules\Support\Modules;
use Modules\Support\RequiresStaffPermission;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Quantités à commander pour les prochaines livraisons (commande de septembre livrée en mars et juin,
 * réassorts d'août et de septembre), calculées sur les ventes projetées, avec export CSV.
 */
class UpcomingOrders extends Page implements HasTable
{
    use HasForecastFilters;
    use InteractsWithTable {
        InteractsWithTable::normalizeTableFilterValuesFromQueryString insteadof HasForecastFilters;
    }
    use RequiresStaffPermission;

    public const DELIVERIES = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string $permission = 'confiserie:view-analytics';

    protected static string|UnitEnum|null $navigationGroup = 'Analyse';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Commandes';

    protected static ?string $title = 'Prochaines commandes';

    protected static ?string $slug = 'analyse/commandes';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(['md' => 2, 'xl' => 4])->schema([
                ...$this->forecastFields(),
                Select::make('margin')
                    ->label('Marge')
                    ->options([0 => 'Aucune', 10 => '+ 10 %', 15 => '+ 15 %', 20 => '+ 20 %', 30 => '+ 30 %'])
                    ->selectablePlaceholder(false),
                Select::make('safety')
                    ->label('Stock de sécurité')
                    ->options([0 => 'Aucun', 1 => 'Faible', 2 => 'Normal', 3 => 'Élevé'])
                    ->helperText('Protège contre les écarts de vente, surtout sur les petits volumes.')
                    ->selectablePlaceholder(false),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('filtersForm'),
            Grid::make(1)->schema(fn (): array => $this->getWidgetsSchemaComponents([OrdersOverview::class])),
            EmbeddedTable::make(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporter (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function table(Table $table): Table
    {
        $deliveries = $this->orders()['deliveries'];

        return $table
            ->heading('Quantités suggérées')
            ->description('En cartons (kg entre parenthèses). Chaque livraison remonte le stock de quoi tenir jusqu\'à la suivante, marge et sécurité comprises. Le contenu d\'un carton est celui du fournisseur principal.')
            ->records(fn (?string $sortColumn, ?string $sortDirection, ?string $search) => $this->orderRecords($sortColumn, $sortDirection, $search))
            ->paginated(false)
            ->groups([
                $this->group('supplier', 'Fournisseur principal'),
                $this->group('brand', 'Marque'),
            ])
            ->groupingSettingsInDropdownOnDesktop()
            ->collapsedGroupsByDefault()
            ->columns([
                TextColumn::make('name')
                    ->label('Bonbon')
                    ->description(fn (array $record) => $record['brand'].' · '.$record['supplier'])
                    ->searchable()
                    ->sortable(),
                Columns::sku(),
                TextColumn::make('per_carton')
                    ->label('Carton')
                    ->suffix(' kg')
                    ->description(fn (array $record) => intdiv($record['per_carton'], max(1, $record['per_bag']))." sacs de {$record['per_bag']} kg")
                    ->sortable()
                    ->alignEnd()
                    ->color('gray'),
                TextColumn::make('stock')->label('Stock')->numeric()->sortable()->suffix(' kg')->alignEnd(),
                ...collect($deliveries)->map(fn (array $delivery, int $k) => TextColumn::make("delivery_{$k}")
                    ->label($delivery['date']->format('d/m/Y'))
                    ->tooltip($delivery['label'])
                    ->sortable()
                    ->alignEnd()
                    ->state(fn (array $record) => $record['cartons'][$k])
                    ->formatStateUsing(fn (int $state, array $record) => $state === 0 ? '—' : "{$state} ({$record['kg'][$k]} kg)")
                    ->color(fn (int $state) => $state === 0 ? 'gray' : null)
                    ->weight(fn (int $state) => $state > 0 ? 'bold' : null))
                    ->all(),
                TextColumn::make('total_cartons')
                    ->label('Total')
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state, array $record) => $state === 0 ? '—' : "{$state} ({$record['total_kg']} kg)"),
            ]);
    }

    /** Regroupement par fournisseur principal ou par marque, avec le total des cartons du groupe en en-tête. */
    private function group(string $column, string $label): Group
    {
        return Group::make($column)
            ->label($label)
            ->collapsible()
            ->getDescriptionFromRecordUsing(function (array $record) use ($column) {
                $rows = collect($this->filteredRows())->where($column, $record[$column]);
                $cartons = $rows->sum('total_cartons');

                return sprintf('%d bonbon(s) — %d carton(s) à commander (%s kg)', $rows->count(), $cartons, number_format($rows->sum('total_kg'), 0, ',', ' '));
            });
    }

    /** @return array{deliveries: list<array{date: CarbonImmutable, kind: string, label: string}>, rows: array<int, array<string, mixed>>} */
    private function orders(): array
    {
        return app(DemandForecast::class)->orders($this->forecastSettings(), CarbonImmutable::today(), self::DELIVERIES);
    }

    /** @return array<int, array<string, mixed>> */
    private function filteredRows(): array
    {
        $brand = filled($this->filters['brand'] ?? null) ? (string) $this->filters['brand'] : null;

        return array_intersect_key($this->orders()['rows'], array_flip(app(SalesHistory::class)->variantIds($brand)));
    }

    /** @return array<int, array<string, mixed>> */
    private function orderRecords(?string $sortColumn, ?string $sortDirection, ?string $search): array
    {
        $rows = collect($this->filteredRows())
            ->map(fn (array $row) => [...$row, ...collect($row['cartons'])->mapWithKeys(fn (int $cartons, int $k) => ["delivery_{$k}" => $cartons])->all()])
            ->when(filled($search), fn ($rows) => $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['name'].' '.$row['brand'].' '.$row['sku']), mb_strtolower($search))));

        $rows = $sortColumn
            ? $rows->sortBy(fn (array $row) => $row[$sortColumn] ?? '', SORT_NATURAL | SORT_FLAG_CASE, $sortDirection === 'desc')
            : $rows->sortBy(fn (array $row) => [mb_strtolower($row['supplier']), mb_strtolower($row['brand']), mb_strtolower($row['name'])]);

        // Groupes contigus : les lignes d'un même fournisseur (ou d'une même marque) se suivent.
        if ($group = $this->getTableGrouping()) {
            $column = $group->getColumn();
            $rows = $rows->sortBy(fn (array $row) => mb_strtolower((string) $row[$column]), SORT_STRING, $this->getTableGroupingDirection() === 'desc');
        }

        return $rows->all();
    }

    private function exportCsv(): StreamedResponse
    {
        $deliveries = $this->orders()['deliveries'];
        $rows = collect($this->filteredRows())->sortBy(fn (array $row) => [mb_strtolower($row['supplier']), mb_strtolower($row['brand']), mb_strtolower($row['name'])]);
        $prices = Modules::enabled('Fournisseurs') ? app(SupplierCatalog::class) : null;

        return response()->streamDownload(function () use ($deliveries, $rows, $prices) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : accents lisibles dans Excel.

            $header = ['Fournisseur principal', 'Marque', 'Bonbon', 'SKU', 'Kg par sac', 'Kg par carton', 'Prix du carton (HT)', 'Stock (kg)'];
            foreach ($deliveries as $delivery) {
                $header[] = $delivery['date']->format('d/m/Y').' — '.$delivery['label'].' (cartons)';
                $header[] = $delivery['date']->format('d/m/Y').' (kg)';
            }
            array_push($header, 'Total (cartons)', 'Total (kg)', 'Montant estimé (HT)');
            fputcsv($out, $header, ';');

            foreach ($rows as $row) {
                $price = $prices?->main($row['product_id'])['carton_price'] ?? null;
                $line = [$row['supplier'], $row['brand'], $row['name'], $row['sku'], $row['per_bag'], $row['per_carton'], self::decimal($price), $row['stock']];
                foreach (array_keys($deliveries) as $k) {
                    $line[] = $row['cartons'][$k];
                    $line[] = $row['kg'][$k];
                }
                array_push($line, $row['total_cartons'], $row['total_kg'], self::decimal($price !== null ? $price * $row['total_cartons'] : null));
                fputcsv($out, $line, ';');
            }

            fclose($out);
        }, 'commandes-'.CarbonImmutable::today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Montant au format français pour le tableur (virgule décimale), vide s'il est inconnu. */
    private static function decimal(?float $amount): string
    {
        return $amount === null ? '' : number_format($amount, 2, ',', '');
    }
}
