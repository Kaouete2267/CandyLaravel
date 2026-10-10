<?php

namespace Modules\Stock\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Stock\Models\StockSetting;
use Modules\Support\RequiresStaffPermission;

/** Réglages du stock : poids d'un sac et contenu d'un carton par défaut (quand le fournisseur ne les précise pas). */
class StockSettings extends Page
{
    use RequiresStaffPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string $permission = 'settings:manage-stock';

    protected static ?string $navigationLabel = 'Réglages du stock';

    protected static ?string $title = 'Réglages du stock';

    protected static ?string $slug = 'reglages-stock';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    public function mount(): void
    {
        $setting = StockSetting::current();

        $this->form->fill(['default_bag_kg' => $setting->default_bag_kg, 'default_carton_kg' => $setting->default_carton_kg]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Conditionnements par défaut')
                ->description('Le stock se compte en kg. Utilisés quand le fournisseur principal d\'un bonbon ne précise pas les siens.')
                ->columns(2)
                ->schema([
                    TextInput::make('default_bag_kg')
                        ->label('Sac (vente)')
                        ->helperText('Une sortie de stock se compte en sacs : chaque sac entamé déduit son poids.')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required()
                        ->suffix('kg'),
                    TextInput::make('default_carton_kg')
                        ->label('Carton (achat)')
                        ->helperText('Les commandes et les réceptions se font en cartons.')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->required()
                        ->multipleOf(fn (Get $get) => max(1, (int) $get('default_bag_kg')))
                        ->validationMessages(['multiple_of' => 'Un carton contient un nombre entier de sacs.'])
                        ->suffix('kg'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Enregistrer')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        StockSetting::current()->update(['default_bag_kg' => (int) $state['default_bag_kg'], 'default_carton_kg' => (int) $state['default_carton_kg']]);

        Notification::make()->success()->title('Réglages enregistrés')->send();
    }
}
