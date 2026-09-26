<?php

namespace Modules\Ia\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Ia\Models\IaSetting;
use UnitEnum;

/**
 * Réglages IA modifiables depuis le backoffice : la clé Gemini ne vivait jusqu'ici que dans le .env,
 * invisible pour qui n'a pas accès au serveur. Utilisée par toutes les fonctionnalités IA du site.
 */
class IaSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Réglages';

    protected static ?string $navigationLabel = 'Réglages IA';

    protected static ?string $title = 'Réglages IA';

    protected static ?string $slug = 'reglages-ia';

    protected string $view = 'ia::pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $setting = IaSetting::current();

        $this->form->fill([
            'gemini_api_key' => $setting->gemini_api_key,
            'gemini_model' => $setting->gemini_model,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Google Gemini')
                ->description('Utilisée par toutes les fonctionnalités IA du site : création de fiche produit, écriture assistée des ingrédients, création d\'allergène, reconnaissance photo.')
                ->schema([
                    TextInput::make('gemini_api_key')
                        ->label('Clé API')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('Obtenue gratuitement sur aistudio.google.com/apikey. Laissez vide pour retomber sur GEMINI_API_KEY du fichier .env, si défini là-bas.'),
                    TextInput::make('gemini_model')
                        ->label('Modèle')
                        ->maxLength(100)
                        ->placeholder(config('bonbon.gemini.model'))
                        ->helperText('Laissez vide pour utiliser le modèle par défaut ('.config('bonbon.gemini.model').').'),
                    Actions::make([
                        Action::make('save')
                            ->label('Enregistrer')
                            ->icon(Heroicon::Check)
                            ->action(fn () => $this->save()),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        IaSetting::current()->update([
            'gemini_api_key' => filled($data['gemini_api_key']) ? $data['gemini_api_key'] : null,
            'gemini_model' => filled($data['gemini_model']) ? $data['gemini_model'] : null,
        ]);

        Notification::make()->success()->title('Réglages IA enregistrés')->send();
    }
}
