<?php

namespace Modules\Stock\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Filament\Resources\StockMovementResource\Pages\ListStockMovements;
use Modules\Stock\Models\StockMovement;
use UnitEnum;

/** Journal en lecture seule de tous les mouvements de stock. */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 21;

    protected static ?string $modelLabel = 'mouvement de stock';

    protected static ?string $pluralModelLabel = 'mouvements de stock';

    protected static ?string $navigationLabel = 'Historique du stock';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['variant.product', 'staff']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('product')
                    ->label('Bonbon')
                    ->state(fn (StockMovement $m) => $m->variant?->product?->attr('name') ?? '—')
                    ->description(fn (StockMovement $m) => $m->variant?->sku)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('variant', fn (Builder $v) => $v
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $p) => $p->where('attribute_data', 'like', "%{$search}%")))),
                TextColumn::make('quantity')
                    ->label('Sacs')
                    ->badge()
                    ->color(fn (int $state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state) => ($state >= 0 ? '+' : '−').abs($state))
                    ->sortable(),
                TextColumn::make('input')
                    ->label('Saisie')
                    ->state(fn (StockMovement $m) => $m->input_unit
                        ? $m->input_quantity.' '.($m->input_unit === 'carton' ? 'carton(s)' : 'sac(s)')
                        : '—'),
                TextColumn::make('stock_after')->label('Stock après')->sortable(),
                TextColumn::make('reason')->label('Motif')->badge(),
                TextColumn::make('staff.first_name')->label('Par')->placeholder('Système'),
                TextColumn::make('note')->label('Note')->placeholder('—')->wrap(),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->label('Motif')
                    ->options(collect(StockReason::cases())->mapWithKeys(fn (StockReason $r) => [$r->value => $r->getLabel()])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
