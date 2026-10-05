<?php

namespace Modules\Ia\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Ia\Models\IaSetting;
use Modules\Ia\Support\AiFeature;
use Modules\Ia\Support\AiFeatures;
use Modules\Ia\Support\AiPromptSection;
use Modules\Ia\Support\AiReplacements;
use Modules\Support\RequiresStaffPermission;

/**
 * Réglages IA modifiables depuis le backoffice : la clé Gemini (qui ne vivait jusqu'ici que dans le .env) et,
 * pour chaque fonction IA enregistrée par les modules ({@see AiFeatures}), sa température et ses sections de
 * prompt. Chaque champ s'enregistre à part ({@see saveField()}) ; seules les valeurs qui diffèrent du défaut
 * du code sont gardées.
 */
class IaSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RequiresStaffPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $permission = 'settings:manage-ai';

    protected static ?string $navigationLabel = 'Réglages IA';

    protected static ?string $title = 'Réglages IA';

    protected static ?string $slug = 'reglages-ia';

    protected string $view = 'ia::pages.settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    public function mount(): void
    {
        $setting = IaSetting::current();

        $this->form->fill([
            'gemini_api_key' => $setting->gemini_api_key,
            'gemini_model' => $setting->gemini_model,
            'features' => collect(app(AiFeatures::class)->all())->map(fn (AiFeature $feature) => [
                'temperature' => $setting->features[$feature->key]['temperature'] ?? null,
                'sections' => collect($feature->sections)
                    ->mapWithKeys(fn (AiPromptSection $section) => [$section->key => $feature->text($section->key)])
                    ->all(),
            ])->all(),
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
                        ->hintAction($this->saveFieldAction('gemini_api_key'))
                        ->helperText('Obtenue gratuitement sur aistudio.google.com/apikey. Laissez vide pour retomber sur GEMINI_API_KEY du fichier .env, si défini là-bas.'),
                    TextInput::make('gemini_model')
                        ->label('Modèle')
                        ->maxLength(100)
                        ->placeholder(config('bonbon.gemini.model'))
                        ->hintAction($this->saveFieldAction('gemini_model'))
                        ->helperText('Laissez vide pour utiliser le modèle par défaut ('.config('bonbon.gemini.model').').'),
                ]),
            ...collect(app(AiFeatures::class)->all())->map(fn (AiFeature $feature) => $this->featureSection($feature))->values()->all(),
        ]);
    }

    /**
     * Enregistre UN champ, sans toucher aux autres : plusieurs personnes peuvent régler la page en même temps
     * sans écraser les modifications des autres. La ligne est relue (verrouillée) juste avant l'écriture, pour
     * repartir de ce que les autres ont enregistré depuis l'ouverture de la page.
     */
    public function saveField(string $statePath): void
    {
        $field = $this->resolveField($statePath);

        if ($field === null) {
            return;
        }

        $this->resetErrorBag("data.{$statePath}");
        $value = data_get($this->data, $statePath);
        $error = $this->fieldError($field, $value);

        if ($error !== null) {
            $this->addError("data.{$statePath}", $error);

            return;
        }

        DB::transaction(function () use ($field, $value) {
            $setting = IaSetting::query()->lockForUpdate()->find(IaSetting::current()->getKey());

            if ($field['feature'] === null) {
                $setting->{$field['name']} = filled($value) ? $value : null;
            } else {
                $setting->features = $this->featuresWith($setting->features ?? [], $field['feature'], $field['section'], $value);
            }

            $setting->save();
        });

        // Un texte vidé retombe sur le défaut : on l'affiche, pour que le champ montre ce qui est réellement utilisé.
        if ($field['section'] !== null && blank($value)) {
            data_set($this->data, $statePath, $field['section']->default);
        }

        Notification::make()->success()->title("« {$this->fieldLabel($field)} » enregistré")->send();
    }

    private function saveFieldAction(string $statePath): Action
    {
        return Action::make('saveField')
            ->label('Enregistrer')
            ->icon(Heroicon::Check)
            ->action(fn () => $this->saveField($statePath));
    }

    private function featureSection(AiFeature $feature): Section
    {
        return Section::make("{$feature->module} — {$feature->label}")
            ->description($feature->description)
            ->collapsible()
            ->collapsed()
            ->schema([
                TextInput::make("features.{$feature->key}.temperature")
                    ->label('Température')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(2)
                    ->step(0.05)
                    ->placeholder((string) $feature->temperature)
                    ->hintAction($this->saveFieldAction("features.{$feature->key}.temperature"))
                    ->helperText("Vide : valeur par défaut ({$feature->temperature}). Plus elle est basse, plus l'IA suit les consignes à la lettre."),
                ...array_map(fn (AiPromptSection $section) => $this->sectionField($feature, $section), $feature->sections),
            ]);
    }

    private function sectionField(AiFeature $feature, AiPromptSection $section): Component
    {
        $statePath = "features.{$feature->key}.sections.{$section->key}";

        $insertions = collect($feature->placeholders($section->key))
            ->map(fn (string $description, string $name) => '{{'.$name.'}} : '.$description)
            ->implode(' · ');

        return Textarea::make($statePath)
            ->label($section->label)
            ->rows(4)
            ->autosize()
            ->hintActions([
                Action::make('resetDefault')
                    ->label('Rétablir le défaut')
                    ->icon(Heroicon::ArrowUturnLeft)
                    ->color('gray')
                    ->action(fn (Set $set) => $set($statePath, $section->default)),
                $this->saveFieldAction($statePath),
            ])
            ->helperText($section->isReplacementList
                ? $section->helperText
                : trim(($section->helperText ?? '').' Vide : texte par défaut. Insertions possibles — '.$insertions));
    }

    /**
     * Le champ désigné par un chemin d'état, ou null si ce chemin n'en désigne aucun (appel forgé).
     *
     * @return ?array{name: string, feature: ?AiFeature, section: ?AiPromptSection}
     */
    private function resolveField(string $statePath): ?array
    {
        if (in_array($statePath, ['gemini_api_key', 'gemini_model'], true)) {
            return ['name' => $statePath, 'feature' => null, 'section' => null];
        }

        $parts = explode('.', $statePath);
        $feature = ($parts[0] ?? null) === 'features' ? (app(AiFeatures::class)->all()[$parts[1] ?? ''] ?? null) : null;

        if ($feature === null) {
            return null;
        }

        if (count($parts) === 3 && $parts[2] === 'temperature') {
            return ['name' => 'temperature', 'feature' => $feature, 'section' => null];
        }

        $section = count($parts) === 4 && $parts[2] === 'sections' ? $feature->section($parts[3]) : null;

        return $section === null ? null : ['name' => $section->key, 'feature' => $feature, 'section' => $section];
    }

    /** @param  array{name: string, feature: ?AiFeature, section: ?AiPromptSection}  $field */
    private function fieldError(array $field, mixed $value): ?string
    {
        $text = (string) $value;

        if ($field['feature'] === null) {
            $max = $field['name'] === 'gemini_api_key' ? 255 : 100;

            return mb_strlen($text) > $max ? "{$max} caractères maximum." : null;
        }

        if ($field['section'] === null) {
            return filled($value) && (! is_numeric($value) || $value < 0 || $value > 2) ? 'Une valeur entre 0 et 2.' : null;
        }

        if ($field['section']->isReplacementList) {
            $invalid = AiReplacements::invalidLines($text);

            return $invalid === [] ? null : 'Format attendu « terme => remplacement », ligne invalide : '.$invalid[0];
        }

        $unknown = $field['feature']->unknownPlaceholders($field['section']->key, $text);

        return $unknown === [] ? null : 'Insertion inconnue ou circulaire : {{'.implode('}}, {{', $unknown).'}}';
    }

    /**
     * Les surcharges de toutes les fonctions, avec la seule valeur de ce champ modifiée. Un texte vidé ou
     * identique au défaut n'est pas gardé : il retombe sur le défaut du code (et en suivra les évolutions).
     *
     * @param  array<string, array{temperature?: float, sections?: array<string, string>}>  $features
     * @return ?array<string, array{temperature?: float, sections?: array<string, string>}>
     */
    private function featuresWith(array $features, AiFeature $feature, ?AiPromptSection $section, mixed $value): ?array
    {
        if ($section === null) {
            $path = 'temperature';
            $override = is_numeric($value) && (float) $value !== $feature->temperature ? (float) $value : null;
        } else {
            $path = "sections.{$section->key}";
            $text = trim(str_replace("\r\n", "\n", (string) $value));
            $override = $text === '' || $text === trim($section->default) ? null : $text;
        }

        $overrides = $features[$feature->key] ?? [];
        $override === null ? Arr::forget($overrides, $path) : Arr::set($overrides, $path, $override);
        $overrides = array_filter($overrides, fn (mixed $entry) => $entry !== []);

        if ($overrides === []) {
            unset($features[$feature->key]);
        } else {
            $features[$feature->key] = $overrides;
        }

        return $features === [] ? null : $features;
    }

    /** @param  array{name: string, feature: ?AiFeature, section: ?AiPromptSection}  $field */
    private function fieldLabel(array $field): string
    {
        return match (true) {
            $field['feature'] === null => $field['name'] === 'gemini_api_key' ? 'Clé API' : 'Modèle',
            $field['section'] === null => "{$field['feature']->label} : température",
            default => "{$field['feature']->label} : {$field['section']->label}",
        };
    }
}
