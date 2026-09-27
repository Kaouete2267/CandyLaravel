<?php

namespace Modules\Allergenes\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Allergenes\Filament\Resources\AllergenResource\Pages\ManageAllergens;
use Modules\Allergenes\Models\Allergen;
use Modules\Support\RequiresStaffPermission;
use Modules\Support\TranslatedFields;
use UnitEnum;

class AllergenResource extends Resource
{
    use RequiresStaffPermission;

    protected static ?string $model = Allergen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string $permission = 'confiserie:manage-allergens';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'allergène';

    protected static ?string $pluralModelLabel = 'allergènes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code technique')
                ->helperText('Identifiant en minuscules (ex. tree_nuts). Ne change plus une fois utilisé.')
                ->required()
                ->alphaDash()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('position')
                ->label('Ordre d\'affichage')
                ->numeric()
                ->default(0),
            TranslatedFields::tabs(['name' => ['label' => 'Nom', 'required' => true]]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                        foreach (array_keys(config('bonbon.locales')) as $locale) {
                            $q->orWhere("name->{$locale}", 'like', "%{$search}%");
                        }
                    })),
                TextColumn::make('code')->label('Code')->badge()->color('gray')->searchable(),
                TextColumn::make('products_count')->label('Produits')->counts('products')->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Model $record) => [...$data, 'name' => $record->getTranslations('name')]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAllergens::route('/')];
    }
}
