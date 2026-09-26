<?php

namespace Modules\Panneaux\Filament\Resources\PanelResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Panneaux\Filament\Resources\PanelResource;

class ListPanels extends ListRecords
{
    protected static string $resource = PanelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau panneau')];
    }
}
