<?php

namespace Modules\Ia\Filament\Resources;

use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Modules\Ia\Filament\Resources\IngredientTermResource\Pages\ManageIngredientTerms;
use Modules\Ia\Models\IngredientTerm;
use Modules\Ia\Services\IngredientGlossary;
use Modules\Ia\Support\IngredientCategory;
use Modules\Support\RequiresStaffPermission;
use UnitEnum;

/**
 * Gestion du glossaire d'ingrédients (voir {@see IngredientGlossary}) : la plupart des lignes sont ajoutées
 * automatiquement (non relues) au fil des scans, l'équipe vient ici corriger la catégorie ou le texte final
 * si besoin et cocher « Relu ». Une même ligne peut regrouper plusieurs formes détectées (« Entrées ») qui
 * désignent le même ingrédient, associées à un seul texte final (« Sortie »).
 *
 * Seul le français se gère ici : les traductions NL/EN restent celles proposées par l'IA lors du tout
 * premier scan de l'ingrédient ({@see IngredientGlossary::resolve()} les laisse de côté si une entrée du
 * glossaire ne les a pas — voir son repli sur `$item[$locale]`), plutôt que d'imposer à l'équipe de gérer
 * trois langues pour un glossaire qui n'a besoin d'être lu/corrigé qu'en français.
 */
class IngredientTermResource extends Resource
{
    use RequiresStaffPermission;

    protected static ?string $model = IngredientTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string $permission = 'confiserie:manage-ingredient-terms';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Glossaire d\'ingrédients';

    protected static ?string $modelLabel = 'ingrédient';

    protected static ?string $pluralModelLabel = 'glossaire d\'ingrédients';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')
                ->label('Catégorie')
                ->helperText('Vide = ingrédient isolé (ex. sucre), ne forme pas de groupe.')
                ->options(collect(IngredientCategory::cases())->mapWithKeys(
                    fn (IngredientCategory $case) => [$case->value => $case->label('fr', plural: true)]
                ))
                ->native(false),
            TagsInput::make('aliases')
                ->label('Entrées')
                ->helperText('Formes détectées sur un emballage qui désignent cet ingrédient (accents/majuscules sans importance : normalisé automatiquement).')
                ->required()
                ->columnSpanFull(),
            TextInput::make('name.fr')
                ->label('Sortie')
                ->helperText('Texte final inséré dans la fiche produit, en français uniquement.')
                ->required(),
            Toggle::make('reviewed')
                ->label('Relu / validé')
                ->helperText('Décoché = ajouté automatiquement par l\'IA, pas encore vérifié par l\'équipe.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('reviewed_at')
            ->columns([
                IconColumn::make('reviewed_at')
                    ->label('Relu')
                    ->boolean()
                    ->getStateUsing(fn (IngredientTerm $record) => $record->reviewed_at !== null),
                TextColumn::make('category')
                    ->label('Catégorie')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state) => $state ? IngredientCategory::from($state)->label('fr', plural: true) : 'isolé')
                    ->sortable(),
                TextColumn::make('aliases')
                    ->label('Entrées')
                    ->wrap(),
                TextColumn::make('name')
                    ->label('Sortie')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('name->fr', 'like', "%{$search}%")),
            ])
            ->filters([
                TernaryFilter::make('reviewed')
                    ->label('Relu')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('reviewed_at'),
                        false: fn (Builder $query) => $query->whereNull('reviewed_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, IngredientTerm $record) => [
                        ...$data,
                        'name' => $record->getTranslations('name'),
                        'reviewed' => $record->reviewed_at !== null,
                    ])
                    ->mutateFormDataUsing(fn (array $data, IngredientTerm $record) => self::prepareForSave($data, $record)),
                DeleteAction::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(fn (array $data) => self::prepareForSave($data, null)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageIngredientTerms::route('/')];
    }

    /**
     * Normalise les entrées (insensibles casse/accents, pour que la recherche du glossaire les retrouve),
     * préserve les traductions NL/EN existantes (le formulaire ne gère que le français — voir le commentaire
     * de la classe), et convertit la case à cocher « Relu » en horodatage.
     *
     * @param  array<string, mixed>  $data
     */
    private static function prepareForSave(array $data, ?IngredientTerm $record): array
    {
        $data['aliases'] = array_values(array_unique(array_map(
            fn (string $alias) => IngredientGlossary::normalize($alias),
            $data['aliases'] ?? []
        )));

        $existingTranslations = $record?->getTranslations('name') ?? [];
        $data['name'] = [
            'fr' => $data['name']['fr'] ?? '',
            'nl' => $existingTranslations['nl'] ?? '',
            'en' => $existingTranslations['en'] ?? '',
        ];

        return [...Arr::except($data, 'reviewed'), 'reviewed_at' => ($data['reviewed'] ?? false) ? now() : null];
    }
}
