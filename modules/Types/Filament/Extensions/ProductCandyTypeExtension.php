<?php

namespace Modules\Types\Filament\Extensions;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Modules\Types\Models\CandyType;
use Modules\Types\Support\ProductCandyType;

/** Ajoute le choix du « Type de bonbon » à la fiche produit Lunar. */
class ProductCandyTypeExtension extends EditPageExtension
{
    public function extendForm(Schema $schema): Schema
    {
        return $schema->components([
            ...$schema->getComponents(withActions: true, withHidden: true),
            Section::make('Type de bonbon')
                ->collapsible()
                ->schema([
                    Select::make('candy_type_id')
                        ->label('Type')
                        ->placeholder('Aucun type')
                        ->options(fn () => CandyType::orderBy('position')->get()->mapWithKeys(fn (CandyType $t) => [$t->id => $t->name])->all()),
                ]),
        ]);
    }

    public function beforeFill(array $data): array
    {
        $data['candy_type_id'] = ProductCandyType::id($this->caller->getRecord());

        return $data;
    }

    public function beforeUpdate(array $data, Model $record): array
    {
        ProductCandyType::assign($record, Arr::pull($data, 'candy_type_id'));

        return $data;
    }
}
