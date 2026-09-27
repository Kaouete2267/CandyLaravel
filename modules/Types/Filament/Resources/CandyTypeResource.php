<?php

namespace Modules\Types\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Support\RequiresStaffPermission;
use Modules\Support\TranslatedFields;
use Modules\Types\Filament\Resources\CandyTypeResource\Pages\ManageCandyTypes;
use Modules\Types\Models\CandyType;
use UnitEnum;

class CandyTypeResource extends Resource
{
    use RequiresStaffPermission;

    protected static ?string $model = CandyType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string $permission = 'confiserie:manage-candy-types';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'type de bonbon';

    protected static ?string $pluralModelLabel = 'types de bonbon';

    protected static ?string $navigationLabel = 'Types de bonbon';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Code technique')
                ->helperText('Identifiant en minuscules (ex. sans_sucre). Ne change plus une fois utilisé.')
                ->required()
                ->alphaDash()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('position')->label('Ordre d\'affichage')->numeric()->default(0),
            ColorPicker::make('color')->label('Couleur de fond')->helperText('Fond de l\'entête des étiquettes de ce type et repère sur le catalogue.'),
            ColorPicker::make('font_color')->label('Couleur du texte')->helperText('Texte de l\'entête des étiquettes de ce type.'),
            TranslatedFields::tabs(['name' => ['label' => 'Nom', 'required' => true]]),
        ])->columns(4);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                ColorColumn::make('color')->label('Fond'),
                ColorColumn::make('font_color')->label('Texte'),
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                        foreach (array_keys(config('bonbon.locales')) as $locale) {
                            $q->orWhere("name->{$locale}", 'like', "%{$search}%");
                        }
                    })),
                TextColumn::make('code')->label('Code')->badge()->color('gray'),
                TextColumn::make('products_count')->label('Bonbons')->counts('products')->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Model $record) => [...$data, 'name' => $record->getTranslations('name')]),
                DeleteAction::make()
                    ->modalDescription('Les bonbons de ce type ne seront pas supprimés : ils n\'auront simplement plus de type.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCandyTypes::route('/')];
    }
}
