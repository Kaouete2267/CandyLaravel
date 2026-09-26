<?php

namespace Modules\Invitations\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Invitations\Filament\Resources\InvitationResource\Pages\ManageInvitations;
use Modules\Invitations\Models\Invitation;
use UnitEnum;

class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'invitation';

    protected static ?string $pluralModelLabel = 'invitations';

    private const STATUS = [
        'active' => ['Active', 'success'],
        'expired' => ['Expirée', 'gray'],
        'revoked' => ['Révoquée', 'danger'],
        'exhausted' => ['Épuisée', 'warning'],
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label('Pour qui ?')
                ->placeholder('Ex. Boulangerie Dupont')
                ->required()
                ->maxLength(255),
            DateTimePicker::make('expires_at')
                ->label('Valable jusqu\'au')
                ->seconds(false)
                ->required()
                ->default(fn () => now()->addDays(config('bonbon.invitations.default_days'))->endOfDay()),
            TextInput::make('max_uses')
                ->label('Ouvertures maximum')
                ->helperText('Nombre de fois où le lien peut être ouvert. Vide = illimité.')
                ->numeric()
                ->minValue(1),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('label')->label('Pour')->searchable()->weight('medium'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS[$state][0])
                    ->color(fn (string $state) => self::STATUS[$state][1]),
                TextColumn::make('expires_at')->label('Expire le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('uses_count')
                    ->label('Ouvertures')
                    ->formatStateUsing(fn (Invitation $record) => $record->max_uses ? "{$record->uses_count} / {$record->max_uses}" : (string) $record->uses_count),
                TextColumn::make('last_used_at')->label('Dernier accès')->since()->placeholder('Jamais'),
                TextColumn::make('url')
                    ->label('Lien')
                    ->copyable()
                    ->copyMessage('Lien copié')
                    ->limit(30)
                    ->tooltip(fn (Invitation $record) => $record->url),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Ouvrir')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Invitation $record) => $record->url, shouldOpenInNewTab: true)
                    ->visible(fn (Invitation $record) => $record->status === 'active'),
                Action::make('extend')
                    ->label('Prolonger de 7 jours')
                    ->icon(Heroicon::OutlinedClock)
                    ->visible(fn (Invitation $record) => $record->revoked_at === null)
                    ->action(function (Invitation $record) {
                        $record->update(['expires_at' => max($record->expires_at, now())->addDays(7)]);
                        Notification::make()->success()->title('Invitation prolongée')->send();
                    }),
                Action::make('revoke')
                    ->label('Révoquer')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Le lien cessera immédiatement de fonctionner, y compris pour les visiteurs déjà connectés.')
                    ->visible(fn (Invitation $record) => $record->revoked_at === null)
                    ->action(fn (Invitation $record) => $record->update(['revoked_at' => now()])),
                Action::make('restore')
                    ->label('Réactiver')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (Invitation $record) => $record->revoked_at !== null)
                    ->action(fn (Invitation $record) => $record->update(['revoked_at' => null])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvitations::route('/')];
    }
}
