<?php

namespace Modules\Panneaux\Filament\Extensions;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\PanelItems;

/** Ajoute « Présence sur les panneaux » à la fiche produit Lunar (le statut et la DLUO se règlent dans le panneau). */
class ProductPanelsExtension extends EditPageExtension
{
    public function extendForm(Schema $schema): Schema
    {
        return $schema->components([
            ...$schema->getComponents(withActions: true, withHidden: true),
            Section::make('Panneaux')
                ->description('Panneaux sur lesquels ce bonbon figure. Actif (en stock) et DLUO se règlent dans l\'onglet Rendu du panneau.')
                ->collapsible()
                ->schema([
                    CheckboxList::make('panel_ids')
                        ->label('Présent sur')
                        ->options(fn () => Panel::orderBy('name')->pluck('name', 'id')->all())
                        ->columns(3),
                ]),
        ]);
    }

    public function beforeFill(array $data): array
    {
        $data['panel_ids'] = $this->caller->getRecord()->panels()->pluck('panels.id')->map(fn ($id) => (string) $id)->all();

        return $data;
    }

    public function beforeUpdate(array $data, Model $record): array
    {
        $wanted = array_map('intval', Arr::pull($data, 'panel_ids', []));
        $current = $record->panels()->pluck('panels.id')->all();

        foreach (array_diff($wanted, $current) as $panelId) {
            PanelItems::add(Panel::findOrFail($panelId), $record);
        }
        foreach (array_diff($current, $wanted) as $panelId) {
            PanelItems::remove(Panel::findOrFail($panelId), $record->id);
        }

        return $data;
    }
}
