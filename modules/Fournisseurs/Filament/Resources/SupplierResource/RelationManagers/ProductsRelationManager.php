<?php

namespace Modules\Fournisseurs\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Models\Product;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Fournisseurs\Support\PackagingFields;
use Modules\Stock\Services\StockService;

/** Bonbons proposés par le fournisseur, avec leur référence, conditionnement et prix chez lui. */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'Bonbons proposés';

    protected static ?string $modelLabel = 'bonbon';

    protected static ?string $pluralModelLabel = 'bonbons';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::pivotFields($this->getOwnerRecord()));
    }

    /** @return array<int, TextInput|Toggle> */
    public static function pivotFields(Supplier $supplier): array
    {
        return [
            TextInput::make('reference')->label('Référence chez le fournisseur')->maxLength(100),
            PackagingFields::bag(fn () => $supplier),
            PackagingFields::carton(fn () => $supplier),
            TextInput::make('carton_price')->label('Prix d\'un carton (HT)')->numeric()->minValue(0)->step(0.01)->prefix('€'),
            Toggle::make('is_main')->label('Fournisseur principal du bonbon')->inline(false),
        ];
    }

    public function table(Table $table): Table
    {
        $supplier = $this->getOwnerRecord();
        $bagKg = fn (Product $record) => $record->pivot->bag_kg ?: $supplier->default_bag_kg ?: StockService::defaults()['bag_kg'];
        $cartonKg = fn (Product $record) => $record->pivot->carton_kg ?: $supplier->default_carton_kg ?: StockService::defaults()['carton_kg'];

        return $table
            ->recordTitle(fn (Product $record) => (string) $record->translateAttribute('name'))
            ->modifyQueryUsing(fn ($query) => $query->with('variants'))
            ->columns([
                TextColumn::make('name')->label('Bonbon')->state(fn (Product $record) => $record->translateAttribute('name')),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->state(fn (Product $record) => $record->variants->first()?->sku)
                    ->url(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
                    ->color('primary'),
                TextColumn::make('reference')->label('Référence')->placeholder('—'),
                TextColumn::make('bag_kg')->label('Sac (vente)')->state($bagKg)->suffix(' kg'),
                TextColumn::make('carton_kg')->label('Carton (achat)')->state($cartonKg)->suffix(' kg')
                    ->description(fn (Product $record) => intdiv($cartonKg($record), max(1, $bagKg($record))).' sacs'),
                TextColumn::make('carton_price')->label('Prix du carton')->money('EUR')->placeholder('—')
                    ->description(fn (Product $record) => $record->pivot->carton_price && $cartonKg($record)
                        ? number_format($record->pivot->carton_price / $cartonKg($record), 2, ',', ' ').' € / kg'
                        : null),
                IconColumn::make('is_main')->label('Principal')->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Ajouter un bonbon')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['attribute_data'])
                    ->schema(fn (AttachAction $action) => [$action->getRecordSelect()->label('Bonbon'), ...self::pivotFields($supplier)]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()->label('Retirer'),
            ]);
    }
}
