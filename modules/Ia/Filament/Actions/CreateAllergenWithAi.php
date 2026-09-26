<?php

namespace Modules\Ia\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Allergenes\Models\Allergen;
use Modules\Ia\Services\AllergenDrafter;
use Modules\Ia\Services\GeminiException;

/** Crée un allergène (code + noms FR/NL/EN) à partir d'un simple nom, traduit par l'IA. */
class CreateAllergenWithAi
{
    public static function make(): Action
    {
        return Action::make('createWithAi')
            ->label('Créer avec l\'IA')
            ->icon(Heroicon::Sparkles)
            ->color('gray')
            ->modalHeading('Créer un allergène avec l\'IA')
            ->modalDescription('Saisissez le nom dans n\'importe quelle langue : le code technique et les traductions sont générés.')
            ->modalSubmitActionLabel('Générer et créer')
            ->schema([
                TextInput::make('name')->label('Nom de l\'allergène')->required()->maxLength(100),
            ])
            ->action(function (array $data) {
                try {
                    $draft = app(AllergenDrafter::class)->draft($data['name']);
                } catch (GeminiException $e) {
                    Notification::make()->danger()->title('L\'IA n\'a pas pu répondre')->body($e->getMessage())->send();

                    return;
                }

                if ($draft['code'] === '' || Allergen::where('code', $draft['code'])->exists()) {
                    Notification::make()->warning()
                        ->title('Allergène déjà présent')
                        ->body("Le code « {$draft['code']} » existe déjà : rien n'a été créé.")
                        ->send();

                    return;
                }

                $allergen = Allergen::create([
                    'code' => $draft['code'],
                    'name' => $draft['name'],
                    'position' => (int) Allergen::max('position') + 1,
                ]);

                Notification::make()->success()
                    ->title('Allergène créé')
                    ->body(collect($allergen->getTranslations('name'))->map(fn ($v, $l) => strtoupper($l).' : '.$v)->implode(' · '))
                    ->send();
            });
    }
}
