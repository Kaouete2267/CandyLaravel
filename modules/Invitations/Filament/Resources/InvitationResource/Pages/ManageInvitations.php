<?php

namespace Modules\Invitations\Filament\Resources\InvitationResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Modules\Invitations\Filament\Resources\InvitationResource;
use Modules\Invitations\Models\Invitation;

class ManageInvitations extends ManageRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvelle invitation')
                ->mutateDataUsing(fn (array $data) => [...$data, 'created_by' => auth('staff')->id()])
                ->successNotification(null)
                ->after(function (Invitation $record) {
                    Notification::make()->success()
                        ->title('Invitation créée')
                        ->body($record->url)
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
