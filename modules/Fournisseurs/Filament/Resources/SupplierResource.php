<?php

namespace Modules\Fournisseurs\Filament\Resources;

use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\Pages\EditSupplier;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use Modules\Fournisseurs\Filament\Resources\SupplierResource\RelationManagers\ProductsRelationManager;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Services\StockService;
use Modules\Support\RequiresStaffPermission;
use UnitEnum;

/** Fiches fournisseurs : coordonnées, conditions, paramètres libres et bonbons proposés. */
class SupplierResource extends Resource
{
    use RequiresStaffPermission;

    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string $permission = 'confiserie:manage-suppliers';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 22;

    protected static ?string $modelLabel = 'fournisseur';

    protected static ?string $pluralModelLabel = 'fournisseurs';

    protected static ?string $navigationLabel = 'Fournisseurs';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'fournisseurs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coordonnées')->columns(2)->schema([
                TextInput::make('name')->label('Nom')->required()->maxLength(255),
                TextInput::make('contact_name')->label('Contact')->maxLength(255),
                TextInput::make('email')->label('E-mail')->email()->maxLength(255),
                TextInput::make('phone')->label('Téléphone')->tel()->maxLength(50),
                Textarea::make('address')->label('Adresse')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Conditions')->columns(2)->schema([
                TextInput::make('default_bag_kg')
                    ->label('Sac (vente)')
                    ->helperText('Poids habituel d\'un sac ; chaque bonbon peut avoir le sien. Une sortie de stock déduit un sac entier.')
                    ->placeholder(fn () => StockService::defaults()['bag_kg'].' (par défaut)')
                    ->integer()->minValue(1)->suffix('kg'),
                TextInput::make('default_carton_kg')
                    ->label('Carton (achat)')
                    ->helperText('Contenu habituel d\'un carton commandé ; chaque bonbon peut avoir le sien.')
                    ->placeholder(fn () => StockService::defaults()['carton_kg'].' (par défaut)')
                    ->integer()->minValue(1)->suffix('kg')
                    ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                        $bag = (int) ($get('default_bag_kg') ?: StockService::defaults()['bag_kg']);

                        if (filled($value) && (int) $value % $bag !== 0) {
                            $fail("Un carton contient un nombre entier de sacs de {$bag} kg.");
                        }
                    }),
                TextInput::make('lead_time_days')->label('Délai de livraison')->integer()->minValue(0)->suffix('jours'),
                KeyValue::make('settings')
                    ->label('Paramètres')
                    ->helperText('Conditions propres au fournisseur : franco de port, minimum de commande, jour de commande…')
                    ->keyLabel('Paramètre')
                    ->valueLabel('Valeur')
                    ->addActionLabel('Ajouter un paramètre')
                    ->columnSpanFull(),
                Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('contact_name')->label('Contact')->placeholder('—')->description(fn (Supplier $s) => $s->email),
                TextColumn::make('phone')->label('Téléphone')->placeholder('—'),
                TextColumn::make('default_bag_kg')->label('Sac')->suffix(' kg')->placeholder('—'),
                TextColumn::make('default_carton_kg')->label('Carton')->suffix(' kg')->placeholder('—'),
                TextColumn::make('lead_time_days')->label('Délai')->suffix(' j')->placeholder('—'),
                TextColumn::make('products_count')->label('Bonbons')->counts('products')->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ProductsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
