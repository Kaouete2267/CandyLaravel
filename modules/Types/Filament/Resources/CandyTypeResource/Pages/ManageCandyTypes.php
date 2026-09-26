<?php

namespace Modules\Types\Filament\Resources\CandyTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Types\Filament\Resources\CandyTypeResource;

class ManageCandyTypes extends ManageRecords
{
    protected static string $resource = CandyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
