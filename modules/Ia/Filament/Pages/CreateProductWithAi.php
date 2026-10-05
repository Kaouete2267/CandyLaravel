<?php

namespace Modules\Ia\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Models\Brand;
use Modules\Allergenes\Enums\AllergenLevel;
use Modules\Allergenes\Models\Allergen;
use Modules\Allergenes\Support\ProductAllergens;
use Modules\Ia\Services\GeminiException;
use Modules\Ia\Services\ImagePayload;
use Modules\Ia\Services\ProductDrafter;
use Modules\Panneaux\Models\Panel;
use Modules\Panneaux\Support\PanelItems;
use Modules\Stock\Enums\StockReason;
use Modules\Stock\Services\StockService;
use Modules\Support\Modules;
use Modules\Support\ProductCreator;
use Modules\Support\RequiresStaffPermission;
use Modules\Support\TranslatedFields;
use UnitEnum;

/**
 * Création assistée par IA : on décrit le bonbon (nom et/ou photos de l'emballage), l'IA propose une fiche
 * complète (traductions, ingrédients, allergènes), l'équipe relit et corrige, puis crée le produit.
 */
class CreateProductWithAi extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use RequiresStaffPermission;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string $permission = 'confiserie:create-product-with-ai';

    protected static string|UnitEnum|null $navigationGroup = 'Confiserie';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Créer avec l\'IA';

    protected static ?string $title = 'Créer un bonbon avec l\'IA';

    protected static ?string $slug = 'creer-avec-ia';

    protected string $view = 'ia::pages.create-product';

    private const TMP_DIR = 'ia-tmp';

    /** @var array<string, mixed> */
    public ?array $start = [];

    /** @var array<string, mixed> */
    public ?array $product = [];

    public bool $analyzed = false;

    public ?string $aiNotes = null;

    /** Photos envoyées (chemins relatifs au disque « local »), verrouillées côté client. */
    #[Locked]
    public array $photoPaths = [];

    public function mount(): void
    {
        $this->startForm->fill();
    }

    public function startForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('start')
            ->components([
                Section::make('1. Point de départ')
                    ->description('Un nom, une description, des photos de l\'emballage (face avant, liste d\'ingrédients) : donnez ce que vous avez.')
                    ->schema([
                        Textarea::make('hint')
                            ->label('Nom ou description')
                            ->placeholder('Ex. Haribo Fraises Tagada, sachet de 3 kg')
                            ->rows(2)
                            ->maxLength(500),
                        FileUpload::make('photos')
                            ->label('Photos (3 maximum)')
                            ->image()
                            ->multiple()
                            ->maxFiles(3)
                            ->maxSize(8192)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('local')
                            ->directory(self::TMP_DIR)
                            ->visibility('private')
                            ->helperText('Plus les photos sont lisibles (surtout la liste d\'ingrédients), plus la fiche sera fiable.'),
                        Actions::make([
                            Action::make('analyze')
                                ->label('Analyser avec l\'IA')
                                ->icon(Heroicon::Sparkles)
                                ->action(fn () => $this->analyze()),
                        ]),
                    ]),
            ]);
    }

    public function productForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('product')
            ->components([
                Section::make('2. Fiche proposée par l\'IA')
                    ->description(fn () => $this->aiNotes
                        ? "À vérifier : {$this->aiNotes}"
                        : 'Relisez et corrigez tout ce qui doit l\'être avant de créer le produit.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('brand')
                            ->label('Marque')
                            ->datalist(fn () => Brand::orderBy('name')->pluck('name')->all())
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('SKU (référence interne)')
                            ->unique('lunar_product_variants', 'sku')
                            ->maxLength(255),
                        TextInput::make('ean')->label('Code-barres (EAN)')->maxLength(50),

                        TranslatedFields::tabs([
                            'name' => ['label' => 'Nom', 'required' => true],
                            'description' => ['label' => 'Description', 'type' => 'textarea'],
                            'ingredients' => ['label' => 'Ingrédients', 'type' => 'textarea'],
                        ]),

                        TextInput::make('bag_weight_kg')->label('Poids d\'un sac')->numeric()->step(0.001)->minValue(0)->suffix('kg'),
                        TextInput::make('bags_per_carton')->label('Sacs par carton')->numeric()->minValue(1)->default(1)->required(),
                        TextInput::make('min_stock_bags')->label('Seuil d\'alerte')->numeric()->minValue(0)->default(0)->suffix('sacs'),
                        TextInput::make('price_per_bag')->label('Prix d\'un sac')->numeric()->step(0.01)->minValue(0)->prefix('€'),
                        TextInput::make('initial_stock')
                            ->label('Stock initial')
                            ->numeric()->minValue(0)->default(0)->suffix('sacs')
                            ->visible(fn () => Modules::enabled('Stock')),
                    ]),

                Section::make('Textes lus sur l\'emballage (scan)')
                    ->description('Recopiés tels quels par l\'IA depuis les photos : ils permettent de vérifier les ingrédients et les allergènes proposés. Enregistrés avec la fiche.')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Textarea::make('ingredients_scan')->label('Ingrédients bruts (scan)')->rows(4),
                        Textarea::make('allergens_scan')->label('Mentions allergènes (scan)')->rows(4),
                    ]),

                Section::make('Allergènes')
                    ->visible(fn () => Modules::enabled('Allergenes'))
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('allergens_contains')
                            ->label(AllergenLevel::Contains->getLabel())
                            ->options(fn () => $this->allergenOptions())
                            ->columns(4)
                            ->live(),
                        CheckboxList::make('allergens_may_contain')
                            ->label(AllergenLevel::MayContain->getLabel())
                            ->options(fn () => $this->allergenOptions())
                            ->disableOptionWhen(fn (string $value, Get $get) => in_array($value, $get('allergens_contains') ?? []))
                            ->columns(4),
                    ]),

                Section::make('Panneaux et publication')
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('panel_ids')
                            ->label('Ajouter aux panneaux')
                            ->options(fn () => Panel::orderBy('name')->pluck('name', 'id')->all())
                            ->columns(2)
                            ->visible(fn () => Modules::enabled('Panneaux')),
                        Toggle::make('use_photo')
                            ->label('Utiliser la première photo comme photo du produit')
                            ->default(true)
                            ->visible(fn () => $this->photoPaths !== []),
                        Toggle::make('publish')
                            ->label('Publier tout de suite')
                            ->helperText('Sinon le produit est créé en brouillon, invisible sur le catalogue.')
                            ->default(false),
                    ]),

                Actions::make([
                    Action::make('create')
                        ->label('Créer le produit')
                        ->icon(Heroicon::Check)
                        ->action(fn () => $this->create()),
                    Action::make('cancel')
                        ->label('Recommencer')
                        ->color('gray')
                        ->action(fn () => $this->startOver()),
                ]),
            ]);
    }

    public function analyze(): void
    {
        $state = $this->startForm->getState();
        $paths = array_values($state['photos'] ?? []);

        try {
            $images = array_map(fn (string $path) => ImagePayload::fromPath(Storage::disk('local')->path($path)), $paths);
            $draft = app(ProductDrafter::class)->draft($state['hint'] ?? null, $images);
        } catch (GeminiException $e) {
            Notification::make()->danger()->title('L\'IA n\'a pas pu répondre')->body($e->getMessage())->send();

            return;
        }

        $this->photoPaths = $paths;
        $this->aiNotes = filled($draft['notes'] ?? null) ? $draft['notes'] : null;

        $byLevel = fn (string $level) => Modules::enabled('Allergenes')
            ? Allergen::whereIn('code', collect($draft['allergens'])->where('level', $level)->pluck('code'))->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];

        $this->productForm->fill([
            'brand' => $draft['brand'] ?? null,
            'name' => $draft['name'] ?? [],
            'description' => $draft['description'] ?? [],
            'ingredients' => $draft['ingredients'] ?? [],
            'ingredients_scan' => $draft['ingredients_scan'] ?? null,
            'allergens_scan' => $draft['allergens_scan'] ?? null,
            'bag_weight_kg' => $draft['bag_weight_kg'] ?? null,
            'ean' => $draft['barcode'] ?? null,
            'bags_per_carton' => 1,
            'min_stock_bags' => 0,
            'initial_stock' => 0,
            'allergens_contains' => $byLevel('contains'),
            'allergens_may_contain' => $byLevel('may_contain'),
            'use_photo' => $paths !== [],
            'publish' => false,
        ]);

        $this->analyzed = true;
    }

    public function create(): void
    {
        $data = $this->productForm->getState();

        $image = ($data['use_photo'] ?? false) && $this->photoPaths !== []
            ? Storage::disk('local')->path($this->photoPaths[0])
            : null;

        $product = ProductCreator::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? [],
            'ingredients' => $data['ingredients'] ?? [],
            'ingredients_scan' => $data['ingredients_scan'] ?? null,
            'allergens_scan' => $data['allergens_scan'] ?? null,
            'brand' => $data['brand'] ?? null,
            'sku' => $data['sku'] ?? null,
            'ean' => $data['ean'] ?? null,
            'bag_weight_kg' => $data['bag_weight_kg'] ?? null,
            'bags_per_carton' => (int) ($data['bags_per_carton'] ?? 1),
            'min_stock_bags' => (int) ($data['min_stock_bags'] ?? 0),
            'price_per_bag' => filled($data['price_per_bag'] ?? null) ? (float) $data['price_per_bag'] : null,
            'status' => ($data['publish'] ?? false) ? 'published' : 'draft',
            'image_path' => $image,
        ]);

        if (Modules::enabled('Allergenes')) {
            ProductAllergens::sync($product, $data['allergens_contains'] ?? [], $data['allergens_may_contain'] ?? []);
        }

        if (Modules::enabled('Panneaux')) {
            foreach ($data['panel_ids'] ?? [] as $panelId) {
                PanelItems::add(Panel::findOrFail((int) $panelId), $product);
            }
        }

        if (Modules::enabled('Stock') && (int) ($data['initial_stock'] ?? 0) > 0) {
            app(StockService::class)->move(
                $product->variants->first(), (int) $data['initial_stock'], StockReason::Receipt,
                staffId: auth('staff')->id(), note: 'Stock initial (création assistée par IA)',
            );
        }

        $this->deleteTemporaryPhotos();

        Notification::make()->success()
            ->title('Produit créé')
            ->body(($data['publish'] ?? false) ? 'Il est visible sur le catalogue.' : 'Il est en brouillon : publiez-le depuis sa fiche.')
            ->actions([
                Action::make('open')->label('Ouvrir la fiche')->url(ProductResource::getUrl('edit', ['record' => $product])),
            ])
            ->persistent()
            ->send();

        $this->startOver();
    }

    public function startOver(): void
    {
        $this->deleteTemporaryPhotos();
        $this->analyzed = false;
        $this->aiNotes = null;
        $this->photoPaths = [];
        $this->productForm->fill();
        $this->startForm->fill();
    }

    /** @return array<int|string, string> */
    private function allergenOptions(): array
    {
        return Allergen::orderBy('position')->get()->mapWithKeys(fn (Allergen $a) => [$a->id => $a->name])->all();
    }

    private function deleteTemporaryPhotos(): void
    {
        foreach ($this->photoPaths as $path) {
            if (str_starts_with($path, self::TMP_DIR.'/')) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
