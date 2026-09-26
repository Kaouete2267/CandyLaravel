<?php

namespace Modules\Panneaux\Filament\Resources\PanelResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Panneaux\Filament\Resources\PanelResource;
use Modules\Panneaux\Support\PanelSettings;

class CreatePanel extends CreateRecord
{
    protected static string $resource = PanelResource::class;

    /** Un nouveau panneau démarre avec les réglages par défaut (les blocs système sont créés avec lui). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['settings'] = PanelSettings::sanitize($data['settings'] ?? []);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return PanelResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
