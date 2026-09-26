<?php

namespace Modules\Panneaux\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Modules\Allergenes\Support\AllergenTerms;
use Modules\Panneaux\Filament\Resources\PanelResource\Pages\CreatePanel;
use Modules\Panneaux\Filament\Resources\PanelResource\Pages\EditPanel;
use Modules\Panneaux\Filament\Resources\PanelResource\Pages\ListPanels;
use Modules\Panneaux\Livewire\PanelBoardComponent;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\BoardData;
use Modules\Panneaux\Support\PanelSettings;
use Modules\Support\Modules;
use UnitEnum;

class PanelResource extends Resource
{
    protected static ?string $model = Panel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'panneau';

    protected static ?string $pluralModelLabel = 'panneaux';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Panneau')
                ->persistTabInQueryString()
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Rendu')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->visibleOn('edit')
                        ->schema([
                            Livewire::make(PanelBoardComponent::class, fn (Panel $record) => ['panelId' => $record->id])
                                ->key('panel-board'),
                        ]),

                    Tab::make('Réglages')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->schema(self::settingsSchema()),

                    Tab::make('Général')
                        ->icon(Heroicon::OutlinedInformationCircle)
                        ->schema([
                            TextInput::make('name')->label('Nom du panneau')->required()->maxLength(255),
                            TextInput::make('location')->label('Emplacement dans le magasin')->maxLength(255),
                            Textarea::make('notes')->label('Notes')->rows(3)->columnSpanFull(),
                        ])->columns(2),
                ]),
        ]);
    }

    /** Réglages du panneau (ils ne concernent que ce panneau). @return array<int, mixed> */
    private static function settingsSchema(): array
    {
        $colors = fn (string $path, string $label) => ColorPicker::make("settings.{$path}")->label($label);
        $section = fn (string $slug) => ['class' => "pnl-sec pnl-sec-{$slug}"];

        return [
            // Fond de couleur sur l'entête de chaque section, pour bien les séparer visuellement les unes des autres.
            Html::make(new HtmlString(<<<'HTML'
                <style>
                    .pnl-sec .fi-section-header { border-bottom: 1px solid rgba(0,0,0,.06); }
                    .dark .pnl-sec .fi-section-header { border-bottom-color: rgba(255,255,255,.08); }
                    .pnl-sec-page .fi-section-header { background: color-mix(in srgb, #6366f1 12%, transparent); }
                    .pnl-sec-titre .fi-section-header { background: color-mix(in srgb, #f59e0b 12%, transparent); }
                    .pnl-sec-etiquettes .fi-section-header { background: color-mix(in srgb, #10b981 12%, transparent); }
                    .pnl-sec-blocs .fi-section-header { background: color-mix(in srgb, #ec4899 12%, transparent); }
                    .pnl-sec-apercu .fi-section-header { background: color-mix(in srgb, #0ea5e9 12%, transparent); }
                    /* Aperçu en direct : une étiquette et un bloc d'information exemples, à l'échelle d'origine. */
                    .pnl-apercu { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; padding: .9rem; background: var(--gray-50); border-radius: .6rem; }
                    .dark .pnl-apercu { background: var(--gray-800); }
                    .pnl-apercu > div { width: 260px; max-width: 100%; }
                    .pnl-apercu-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin: 0 0 .35rem; }
                </style>
                HTML
            )),

            Section::make('Aperçu en direct')->extraAttributes($section('apercu'))
                ->description('Se met à jour à chaque changement des réglages « Étiquettes de bonbons » et « Blocs d\'information » ci-dessous.')
                ->schema([self::livePreview()]),

            Section::make('Mise en page')->columns(3)->extraAttributes($section('page'))->schema([
                Select::make('settings.page.orientation')->label('Orientation')->options(PanelSettings::ORIENTATIONS)->required(),
                Select::make('settings.page.columnsPerRow')->label('Étiquettes par ligne')
                    ->options(array_combine(PanelSettings::COLUMNS_PER_ROW, PanelSettings::COLUMNS_PER_ROW))->required()
                    ->helperText('Nombre de colonnes d\'étiquettes sur une page.'),
                Toggle::make('settings.showDluo')->label('Afficher la DLUO')->live()
                    ->helperText('Saisie de la DLUO et avertissements d\'expiration.'),
                Toggle::make('settings.page.dluo')->label('Page récapitulative des DLUO')
                    ->visible(fn (Get $get) => (bool) $get('settings.showDluo')),
            ]),

            Section::make('Titre du panneau')->columns(3)->collapsible()->extraAttributes($section('titre'))->schema([
                Textarea::make('settings.titre.content')->label('Texte')->rows(3)->columnSpanFull()
                    ->helperText('Une ligne = une page, sauf si « sur la première page seulement » est activé.'),
                Toggle::make('settings.titre.ononepage')->label('Sur la première page seulement'),
                Select::make('settings.titre.font')->label('Police')->options(array_combine(PanelSettings::FONTS, PanelSettings::FONTS)),
                TextInput::make('settings.titre.size')->label('Taille (px)')->numeric()->minValue(8)->maxValue(300),
                $colors('titre.color', 'Couleur'),
                $colors('titre.bordercolor', 'Couleur du contour'),
            ]),

            Section::make('Étiquettes de bonbons')->columns(3)->collapsible()->extraAttributes($section('etiquettes'))->schema([
                $colors('bonbon.bordercolor', 'Couleur de la bordure')->live(onBlur: true),
                Select::make('settings.bonbon.allergene')->label('Mise en valeur des allergènes')->live()
                    ->options(['background' => 'Fond coloré (surlignage)', 'color' => 'Texte coloré'])
                    ->helperText('Les allergènes eux-mêmes viennent du module Allergènes (menu Confiserie → Allergènes) : rien à saisir ici.'),
                ...self::fontFields('bonbon.name', 'Nom du bonbon'),
                ...self::fontFields('bonbon.ing', 'Ingrédients'),
            ]),

            Section::make('Blocs d\'information')->columns(3)->collapsible()->extraAttributes($section('blocs'))
                ->description('Style commun aux blocs d\'information. Le contenu, la position et la largeur de chaque bloc se règlent dans l\'onglet Rendu.')->schema([
                    $colors('info.bordercolor', 'Couleur de la bordure')->live(onBlur: true),
                    $colors('info.bgcolor.head', 'Fond du titre')->live(onBlur: true),
                    $colors('info.bgcolor.body', 'Fond du contenu')->live(onBlur: true),
                    ...self::fontFields('info.title', 'Titre'),
                    ...self::fontFields('info.content', 'Contenu'),
                ]),
        ];
    }

    /** Police = famille, taille, couleur ; réactive (pour l'aperçu en direct). @return array<int, mixed> */
    private static function fontFields(string $path, string $label): array
    {
        return [
            Fieldset::make($label)->columnSpanFull()->columns(3)->schema([
                Select::make("settings.{$path}.0")->label('Police')->options(array_combine(PanelSettings::FONTS, PanelSettings::FONTS))->live(),
                TextInput::make("settings.{$path}.1")->label('Taille (px)')->numeric()->minValue(6)->maxValue(200)->live(onBlur: true),
                ColorPicker::make("settings.{$path}.2")->label('Couleur')->live(onBlur: true),
            ]),
        ];
    }

    /**
     * Étiquette et bloc d'information exemples, redessinés à chaque changement des réglages de style
     * (mêmes vues/classes CSS que le rendu réel : `panneaux::partials.label` + `board-style`).
     */
    private static function livePreview(): Html
    {
        return Html::make(function (Get $get) {
            $s = PanelSettings::sanitize([
                'showDluo' => (bool) $get('settings.showDluo'),
                'page' => (array) $get('settings.page'),
                'titre' => (array) $get('settings.titre'),
                'bonbon' => (array) $get('settings.bonbon'),
                'info' => (array) $get('settings.info'),
            ]);

            $sampleIngredients = 'Sucre, sirop de glucose, gélatine de porc, lait en poudre, arôme. '
                .'Sans gluten. Peut contenir des traces de fruits à coque.';

            $label = [
                'id' => 0, 'name' => 'Bonbon exemple', 'brand' => null, 'logo' => null,
                'type' => null, 'color' => null, 'ink' => null, 'dluo' => null,
                'ingredients' => Modules::enabled('Allergenes') ? AllergenTerms::highlight($sampleIngredients) : e($sampleIngredients),
            ];

            $infoHtml = '<div class="etq etq-info" style="border:1px solid '.e($s['info']['bordercolor']).'">'
                .'<div class="title" style="background-color:'.e($s['info']['bgcolor']['head']).';'.BoardData::font($s['info']['title']).'">Exemple de bloc d\'information</div>'
                .'<div class="corp" style="background-color:'.e($s['info']['bgcolor']['body']).';'.BoardData::font($s['info']['content']).'">'
                .'Ceci est un aperçu du style. Le contenu, la position et la largeur de chaque bloc se règlent dans l\'onglet Rendu.</div></div>';

            return new HtmlString(
                view('panneaux::partials.board-style')->render()
                .'<div class="pnl-allergene-'.e($s['bonbon']['allergene']).' pnl-apercu">'
                .'<div><p class="pnl-apercu-label">Étiquette</p>'.view('panneaux::partials.label', ['l' => $label, 's' => $s])->render().'</div>'
                .'<div><p class="pnl-apercu-label">Bloc d\'information</p>'.$infoHtml.'</div>'
                .'</div>'
            );
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Panneau')->searchable()->sortable(),
                TextColumn::make('location')->label('Emplacement')->placeholder('—'),
                TextColumn::make('blocks_count')->label('Blocs')->counts('blocks'),
                TextColumn::make('products_count')->label('Bonbons')->counts('products'),
            ])
            ->recordActions([
                EditAction::make()->label('Ouvrir'),
                Action::make('print')
                    ->label('Imprimer')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (Panel $record) => route('filament.lunar.panneaux.print', ['panel' => $record->id, 'auto' => 1]), shouldOpenInNewTab: true),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPanels::route('/'),
            'create' => CreatePanel::route('/create'),
            'edit' => EditPanel::route('/{record}/edit'),
        ];
    }
}
