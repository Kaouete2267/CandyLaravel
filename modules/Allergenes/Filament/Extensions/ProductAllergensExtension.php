<?php

namespace Modules\Allergenes\Filament\Extensions;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Modules\Allergenes\Enums\AllergenLevel;
use Modules\Allergenes\Models\Allergen;
use Modules\Allergenes\Support\ProductAllergens;

/** Ajoute la section « Allergènes » à la fiche produit Lunar. */
class ProductAllergensExtension extends EditPageExtension
{
    public function extendForm(Schema $schema): Schema
    {
        $options = fn () => Allergen::orderBy('position')->get()->mapWithKeys(fn (Allergen $a) => [$a->id => $a->name])->all();

        return $schema->components([
            ...$schema->getComponents(withActions: true, withHidden: true),
            Section::make('Allergènes')
                ->description('Déclarés sur la fiche publique ; les visiteurs peuvent filtrer le catalogue pour les éviter.')
                ->collapsible()
                ->schema([
                    CheckboxList::make('allergens_contains')
                        ->label(AllergenLevel::Contains->getLabel())
                        ->options($options)
                        ->columns(4)
                        ->live(),
                    CheckboxList::make('allergens_may_contain')
                        ->label(AllergenLevel::MayContain->getLabel())
                        ->options($options)
                        ->disableOptionWhen(fn (string $value, Get $get) => in_array($value, $get('allergens_contains') ?? []))
                        ->columns(4),
                ]),
        ]);
    }

    public function beforeFill(array $data): array
    {
        $record = $this->caller->getRecord()->loadMissing('allergens');

        $data['allergens_contains'] = ProductAllergens::ids($record, AllergenLevel::Contains);
        $data['allergens_may_contain'] = ProductAllergens::ids($record, AllergenLevel::MayContain);

        return $data;
    }

    public function beforeUpdate(array $data, Model $record): array
    {
        ProductAllergens::sync(
            $record,
            Arr::pull($data, 'allergens_contains', []),
            Arr::pull($data, 'allergens_may_contain', []),
        );

        return $data;
    }
}
