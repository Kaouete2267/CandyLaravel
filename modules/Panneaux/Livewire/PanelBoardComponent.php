<?php

namespace Modules\Panneaux\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Modules\Allergenes\Support\AllergenDeclaration;
use Modules\Panneaux\Models\Block;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\BoardData;
use Modules\Panneaux\Support\PanelItems;
use Modules\Panneaux\Support\PanelWarnings;
use Modules\Support\Modules;

/**
 * Onglet « Rendu » de l'édition d'un panneau : le panneau tel qu'il s'imprime, la gestion de ses blocs
 * (marque automatique, manuel, système, information) et de ses bonbons (actif, DLUO), les avertissements.
 */
class PanelBoardComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $panelId;

    public string $search = '';

    public function mount(int $panelId): void
    {
        $this->panelId = $panelId;
        $this->panel->ensureSystemBlocks();
    }

    /** L'onglet « Réglages » vient d'être enregistré : on relit tout. */
    #[On('panel-saved')]
    public function reload(): void
    {
        unset($this->panel, $this->data, $this->members, $this->warnings);
    }

    #[Computed]
    public function panel(): Panel
    {
        return Panel::findOrFail($this->panelId);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function data(): array
    {
        return BoardData::for($this->panel);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function warnings(): array
    {
        return PanelWarnings::for($this->panel);
    }

    /** Bonbons du panneau avec leur statut, filtrés par la recherche. @return array<int, array<string, mixed>> */
    #[Computed]
    public function members(): array
    {
        $rows = $this->panel->products()->with('brand')->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => (string) $p->translateAttribute('name'),
            'brand' => $p->brand?->name,
            'published' => $p->status === 'published',
            'active' => (bool) $p->pivot->active,
            'dluo' => $p->pivot->dluo,
            'block' => $this->manualBlockId($p->id),
        ])->sortBy(fn ($r) => Str::lower(Str::ascii($r['name'])));

        if (($needle = Str::lower(Str::ascii(trim($this->search)))) !== '') {
            $rows = $rows->filter(fn ($r) => str_contains(Str::lower(Str::ascii($r['name'].' '.$r['brand'])), $needle));
        }

        return $rows->values()->all();
    }

    private function manualBlockId(int $productId): ?int
    {
        return $this->panel->blocks->firstWhere(fn (Block $b) => $b->kind === Block::KIND_MANUAL && $b->products->contains('id', $productId))?->id;
    }

    // ------------------------------------------------------------------ blocs : actions modales

    public function createBlockAction(): Action
    {
        return Action::make('createBlock')
            ->label('Nouveau bloc')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Nouveau bloc')
            ->modalSubmitActionLabel('Créer')
            ->modalWidth('4xl')
            ->schema([
                Select::make('kind')
                    ->label('Sorte de bloc')
                    ->options([
                        Block::KIND_BRAND => 'Marque : alimenté automatiquement par une marque',
                        Block::KIND_MANUAL => 'Manuel : on choisit soi-même les bonbons',
                        Block::KIND_CUSTOM => 'Information : titre et contenu libres',
                    ])
                    ->default(Block::KIND_BRAND)
                    ->required()
                    ->live(),
                Select::make('brand_id')
                    ->label('Marque')
                    ->placeholder('Sans marque')
                    ->options(fn () => Brand::orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get) => $get('kind') === Block::KIND_BRAND),
                TextInput::make('title')->label('Titre')->maxLength(255)
                    ->helperText('Bandeau du bloc. Pour un bloc marque, vide = nom de la marque.'),
                Group::make($this->customContentFields())
                    ->visible(fn (Get $get) => $get('kind') === Block::KIND_CUSTOM)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) {
                $kind = $data['kind'];
                $isInfo = $kind === Block::KIND_CUSTOM;
                $zone = $isInfo ? 'after' : 'labels';
                $brand = $kind === Block::KIND_BRAND && $data['brand_id'] ? Brand::find($data['brand_id']) : null;

                $this->panel->blocks()->create([
                    'kind' => $kind,
                    'brand_id' => $brand?->id,
                    'zone' => $zone,
                    'code' => $isInfo ? 'custom-'.Str::lower(Str::random(6)) : $this->nextCode(),
                    'name' => $brand?->name,
                    'title' => filled($data['title']) ? $data['title'] : null,
                    'content' => $isInfo ? ($data['content'] ?? '') : null,
                    'position' => (int) $this->panel->blocks()->where('zone', $zone)->max('position') + 1,
                    'width' => 6,
                ]);

                $this->forget();
            });
    }

    public function editBlockAction(): Action
    {
        return Action::make('editBlock')
            ->modalHeading(fn (array $arguments) => 'Modifier le bloc « '.($this->block($arguments['block'])->displayTitle() ?: $this->block($arguments['block'])->code).' »')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Enregistrer')
            ->schema(fn (array $arguments) => $this->blockSchema($this->block($arguments['block'])))
            // La suppression (si permise) se fait depuis cette fenêtre plutôt qu'un bouton séparé sur la ligne :
            // un bloc système n'a simplement pas ce bouton (son verrou est expliqué dans le formulaire ci-dessus).
            ->extraModalFooterActions(fn (array $arguments) => $this->block($arguments['block'])->isDeletable() ? [
                Action::make('deleteBlock')
                    ->label('Supprimer ce bloc')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Les bonbons ne sont pas supprimés du panneau : un bloc marque se recrée automatiquement, et les bonbons d\'un bloc manuel ne sont plus affichés tant qu\'ils n\'ont pas de bloc.')
                    // Ferme aussi la fenêtre « Modifier le bloc » parente : sinon elle resterait ouverte sur un
                    // bloc qui vient d'être supprimé, et le prochain rendu planterait en le cherchant à nouveau.
                    ->cancelParentActions()
                    ->action(function () use ($arguments) {
                        $this->block($arguments['block'])->delete();
                        $this->forget();
                    }),
            ] : [])
            ->fillForm(function (array $arguments) {
                $block = $this->block($arguments['block']);

                return [
                    'active' => $block->active,
                    'code' => $block->code,
                    'brand_id' => $block->brand_id,
                    'name' => $block->name,
                    'title' => $block->title,
                    'zone' => $block->zone,
                    'width' => $block->width,
                    'repeat' => $block->repeat,
                    'show_header' => $block->show_header,
                    'showPercent' => (bool) $block->option('showPercent', true),
                    'content' => $block->content,
                ];
            })
            ->action(function (array $data, array $arguments) {
                $block = $this->block($arguments['block']);

                $values = [
                    'active' => (bool) ($data['active'] ?? true),
                    'title' => filled($data['title'] ?? null) ? $data['title'] : null,
                ];

                if ($block->isInfo()) {
                    $values += [
                        'width' => max(1, min(Block::GRID_COLUMNS, (int) ($data['width'] ?? 6))),
                        'repeat' => (bool) ($data['repeat'] ?? false),
                    ];

                    if (in_array($data['zone'] ?? null, ['before', 'after'], true) && $data['zone'] !== $block->zone) {
                        $values['zone'] = $data['zone'];
                        $values['position'] = (int) $this->panel->blocks()->where('zone', $data['zone'])->max('position') + 1;
                    }
                }

                if ($block->kind === Block::KIND_MARK) {
                    $values['options'] = [...($block->options ?? []), 'showPercent' => (bool) ($data['showPercent'] ?? true)];
                }
                if ($block->kind === Block::KIND_CUSTOM) {
                    $values['content'] = $data['content'] ?? '';
                }
                if (! $block->isInfo()) {
                    $values += ['show_header' => (bool) ($data['show_header'] ?? true), 'code' => $data['code'] ?? $block->code];
                    if ($block->kind === Block::KIND_MANUAL) {
                        $values['name'] = $data['name'] ?? null;
                    }
                    if ($block->kind === Block::KIND_BRAND) {
                        $brand = ! empty($data['brand_id']) ? Brand::find($data['brand_id']) : null;
                        $values += ['brand_id' => $brand?->id, 'name' => $brand?->name];
                    }
                }

                $block->update($values);
                $this->forget();
            });
    }

    public function addProductsAction(): Action
    {
        return Action::make('addProducts')
            ->label('Ajouter des bonbons')
            ->icon(Heroicon::OutlinedPlus)
            ->color('gray')
            ->size('sm')
            ->modalHeading('Ajouter des bonbons au panneau')
            ->modalSubmitActionLabel('Ajouter')
            ->schema([
                Select::make('products')
                    ->label('Bonbons')
                    ->multiple()
                    ->searchable()
                    ->required()
                    ->options(fn () => Product::whereNotIn('id', $this->panel->products()->pluck('lunar_products.id'))->with('brand')->get()
                        ->mapWithKeys(fn (Product $p) => [$p->id => trim($p->translateAttribute('name').($p->brand ? ' — '.$p->brand->name : ''))])
                        ->sort()->all()),
                TextInput::make('dluo')->label('DLUO (MM/AAAA)')->placeholder('03/2027')
                    ->helperText('Facultatif, informatif : suivi et avertissements d\'expiration.')
                    ->rule(fn () => fn ($attribute, $value, $fail) => filled($value) && PanelItems::normalizeDluo($value) === null ? $fail('Format attendu : MM/AAAA.') : null),
            ])
            ->action(function (array $data) {
                foreach ($data['products'] as $id) {
                    PanelItems::add($this->panel, (int) $id, $data['dluo'] ?? null);
                }
                $this->forget();
            });
    }

    /** @return array<int, mixed> */
    private function blockSchema(Block $block): array
    {
        $fields = [];

        if ($block->isSystem()) {
            $fields[] = Text::make('Bloc système : modifiable (actif, titre, position, largeur…) mais non supprimable — vous pouvez le désactiver s\'il ne sert pas.')
                ->icon(Heroicon::OutlinedLockClosed)->color('gray')->columnSpanFull();
        }

        $fields[] = Toggle::make('active')->label('Actif')->helperText('Un bloc inactif n\'est ni affiché ni imprimé.');
        $fields[] = TextInput::make('title')->label('Titre')->maxLength(255)
            ->helperText($block->isInfo() ? null : 'Bandeau du bloc. Vide = nom de la marque / du bloc.');

        if ($block->isInfo()) {
            $fields[] = Select::make('zone')->label('Position')->options(['before' => 'Avant les étiquettes', 'after' => 'Après les étiquettes'])->required();
            $fields[] = TextInput::make('width')->label('Largeur (sur 12)')->numeric()->minValue(1)->maxValue(Block::GRID_COLUMNS)->required()
                ->helperText('6 = la moitié de la page, 4 = un tiers, 12 = toute la largeur.');
            $fields[] = Toggle::make('repeat')->label('Répéter sur chaque page');
        }

        if ($block->kind === Block::KIND_MARK) {
            $fields[] = Toggle::make('showPercent')->label('Afficher les pourcentages');
        }

        if ($block->kind === Block::KIND_CUSTOM) {
            array_push($fields, ...$this->customContentFields());
        }

        if (! $block->isInfo()) {
            $fields[] = TextInput::make('code')->label('Code du bloc')->required()->maxLength(20)
                ->unique(table: 'blocks', column: 'code', modifyRuleUsing: fn (Unique $rule) => $rule->where('panel_id', $this->panelId)->ignore($block->id));
            $fields[] = Toggle::make('show_header')->label('Afficher le bandeau du bloc');
            $fields[] = $block->kind === Block::KIND_BRAND
                ? Select::make('brand_id')->label('Marque')->placeholder('Sans marque')->options(fn () => Brand::orderBy('name')->pluck('name', 'id')->all())->searchable()
                    ->helperText('Alimenté automatiquement : tous les bonbons de cette marque présents sur le panneau.')
                : TextInput::make('name')->label('Nom')->maxLength(255);
        }

        return $fields;
    }

    /** Champs du contenu libre d'un bloc « Information », partagés entre création et édition. @return array<int, mixed> */
    private function customContentFields(): array
    {
        $fields = [];

        if (Modules::enabled('Allergenes')) {
            $fields[] = Actions::make([
                Action::make('generateAllergenContent')
                    ->label('Générer depuis le module Allergènes')
                    ->icon(Heroicon::Sparkles)
                    ->color('gray')
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalDescription('Remplace le contenu actuel de ce bloc par un texte généré à partir des allergènes déclarés dans le module Allergènes (menu Confiserie → Allergènes). Vous pourrez ensuite le modifier librement.')
                    ->action(fn (Set $set) => $set('content', AllergenDeclaration::html())),
            ])->columnSpanFull();
        }

        $fields[] = RichEditor::make('content')
            ->label('Contenu')
            ->toolbarButtons([['bold', 'italic', 'underline'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['link', 'horizontalRule']])
            ->helperText('Pour un besoin ponctuel (ex. allergènes majeurs), le bouton ci-dessus propose un texte de départ que vous restez libre de modifier.')
            ->columnSpanFull();

        return $fields;
    }

    // ------------------------------------------------------------------ blocs : actions rapides

    public function growBlock(int $id): void
    {
        $this->changeWidth($id, +1);
    }

    public function shrinkBlock(int $id): void
    {
        $this->changeWidth($id, -1);
    }

    private function changeWidth(int $id, int $delta): void
    {
        $block = $this->block($id);
        $block->update(['width' => max(1, min(Block::GRID_COLUMNS, $block->width + $delta))]);
        $this->forget();
    }

    /** Monte / descend un bloc parmi ceux de son côté (avant, étiquettes ou après). */
    public function moveBlock(int $id, int $direction): void
    {
        $block = $this->block($id);
        $ids = $this->panel->blocks()->where('zone', $block->zone)->pluck('id')->all();
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];
        foreach ($ids as $position => $blockId) {
            Block::whereKey($blockId)->update(['position' => $position + 1]);
        }
        $this->forget();
    }

    public function toggleZone(int $id): void
    {
        $block = $this->block($id);

        if (! $block->isInfo()) {
            return;
        }

        $zone = $block->zone === 'before' ? 'after' : 'before';
        $block->update(['zone' => $zone, 'position' => (int) $this->panel->blocks()->where('zone', $zone)->max('position') + 1]);
        $this->forget();
    }

    public function toggleBlock(int $id): void
    {
        $block = $this->block($id);
        $block->update(['active' => ! $block->active]);
        $this->forget();
    }

    /** Crée les blocs marque des marques présentes sur le panneau que plus aucun bloc n'affiche. */
    public function createMissingBlocks(): void
    {
        foreach (collect($this->data['orphans'])->unique('brand_id') as $orphan) {
            $brand = $orphan['brand_id'] ? Brand::find($orphan['brand_id']) : null;

            $this->panel->blocks()->firstOrCreate(
                ['kind' => Block::KIND_BRAND, 'brand_id' => $brand?->id],
                [
                    'zone' => 'labels',
                    'code' => $this->nextCode(),
                    'name' => $brand?->name,
                    'position' => (int) $this->panel->blocks()->where('zone', 'labels')->max('position') + 1,
                ],
            );
        }

        $this->forget();
    }

    // ------------------------------------------------------------------ bonbons du panneau

    public function toggleProduct(int $productId): void
    {
        $member = collect($this->members)->firstWhere('id', $productId);
        PanelItems::setActive($this->panel, $productId, ! ($member['active'] ?? true));
        $this->forget();
    }

    public function updateDluo(int $productId, ?string $value): void
    {
        if (! PanelItems::setDluo($this->panel, $productId, $value)) {
            Notification::make()->warning()->title('DLUO invalide')->body('Format attendu : MM/AAAA (ex. 03/2027).')->send();
        }
        $this->forget();
    }

    public function removeProduct(int $productId): void
    {
        PanelItems::remove($this->panel, $productId);
        $this->forget();
    }

    /** Place un bonbon dans un bloc manuel du panneau (ou l'en retire si $blockId est vide). */
    public function assignProduct(int $productId, $blockId = null): void
    {
        if (blank($blockId)) {
            DB::table('block_product')->where('product_id', $productId)->whereIn('block_id', $this->panel->blocks()->pluck('id'))->delete();
        } else {
            $block = $this->block((int) $blockId);
            abort_unless($block->kind === Block::KIND_MANUAL, 422);
            PanelItems::assignToBlock($this->panel, $productId, $block);
        }
        $this->forget();
    }

    // ------------------------------------------------------------------ interne

    private function block(int|string $id): Block
    {
        return Block::where('panel_id', $this->panelId)->findOrFail((int) $id);
    }

    /** A, B, … Z, AA… : premier code libre pour un bloc d'étiquettes. */
    private function nextCode(): string
    {
        $used = $this->panel->blocks()->pluck('code')->all();
        $index = 0;

        do {
            $code = '';
            for ($n = $index; ; $n = intdiv($n, 26) - 1) {
                $code = chr(65 + $n % 26).$code;
                if ($n < 26) {
                    break;
                }
            }
            $index++;
        } while (in_array($code, $used, true));

        return $code;
    }

    private function forget(): void
    {
        unset($this->panel, $this->data, $this->members, $this->warnings);
    }

    public function render()
    {
        return view('panneaux::livewire.panel-board');
    }
}
