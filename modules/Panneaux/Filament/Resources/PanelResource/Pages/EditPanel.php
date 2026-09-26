<?php

namespace Modules\Panneaux\Filament\Resources\PanelResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Modules\Panneaux\Filament\Resources\PanelResource;
use Modules\Panneaux\Support\PanelSettings;

class EditPanel extends EditRecord
{
    protected static string $resource = PanelResource::class;

    // L'aperçu du panneau et le panneau latéral ont besoin de toute la largeur de l'écran
    // (la largeur par défaut de Filament, 7xl, les compresse inutilement).
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** Réglages complétés par les valeurs par défaut. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['settings'] = PanelSettings::merge($data['settings'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['settings'] = PanelSettings::sanitize($data['settings'] ?? []);

        return $data;
    }

    /** L'onglet Rendu relit les réglages qui viennent d'être enregistrés. */
    protected function afterSave(): void
    {
        $this->dispatch('panel-saved');
    }
}
