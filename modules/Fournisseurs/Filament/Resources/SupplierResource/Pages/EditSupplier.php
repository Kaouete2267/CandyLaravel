<?php

namespace Modules\Fournisseurs\Filament\Resources\SupplierResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Fournisseurs\Filament\Resources\SupplierResource;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
