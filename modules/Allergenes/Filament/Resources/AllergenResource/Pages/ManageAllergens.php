<?php

namespace Modules\Allergenes\Filament\Resources\AllergenResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\Allergenes\Filament\Resources\AllergenResource;
use Modules\Ia\Filament\Actions\CreateAllergenWithAi;
use Modules\Support\Modules;

class ManageAllergens extends ManageRecords
{
    protected static string $resource = AllergenResource::class;

    protected function getHeaderActions(): array
    {
        return array_filter([
            // Action fournie par le module Ia : absente si le module est désactivé.
            Modules::enabled('Ia') ? CreateAllergenWithAi::make() : null,
            CreateAction::make(),
        ]);
    }
}
