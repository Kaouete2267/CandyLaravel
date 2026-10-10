<?php

namespace Modules\Fournisseurs\Filament\Extensions;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lunar\Admin\Support\Extending\EditPageExtension;
use Lunar\Models\Product;
use Modules\Fournisseurs\Models\ProductSupplier;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Fournisseurs\Support\PackagingFields;

/** Ajoute la section « Fournisseurs » à la fiche produit Lunar : prix et conditionnements chez chacun, et le principal. */
class ProductSuppliersExtension extends EditPageExtension
{
    public function extendForm(Schema $schema): Schema
    {
        $supplier = fn (Get $get) => filled($get('supplier_id')) ? Supplier::find($get('supplier_id')) : null;

        return $schema->components([
            ...$schema->getComponents(withActions: true, withHidden: true),
            Section::make('Fournisseurs')
                ->description('Le fournisseur principal donne les conditionnements du bonbon : le sac (vente, base des sorties de stock) et le carton (achat, base des commandes).')
                ->collapsible()
                ->schema([
                    Repeater::make('supplier_links')
                        ->hiddenLabel()
                        ->addActionLabel('Ajouter un fournisseur')
                        ->defaultItems(0)
                        ->columns(6)
                        ->schema([
                            Select::make('supplier_id')
                                ->label('Fournisseur')
                                ->options(fn () => Supplier::orderBy('name')->pluck('name', 'id')->all())
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->required()
                                ->live(),
                            TextInput::make('reference')->label('Référence')->maxLength(100),
                            PackagingFields::bag($supplier),
                            PackagingFields::carton($supplier),
                            TextInput::make('carton_price')->label('Prix du carton (HT)')->numeric()->minValue(0)->step(0.01)->prefix('€'),
                            Toggle::make('is_main')
                                ->label('Principal')
                                ->inline(false)
                                ->live()
                                // Un seul principal : en cocher un décoche les autres.
                                ->afterStateUpdated(function (bool $state, Toggle $component, Get $get, Set $set) {
                                    if (! $state) {
                                        return;
                                    }

                                    $current = Str::afterLast($component->getContainer()->getStatePath(), '.');
                                    foreach (array_keys($get('../../supplier_links') ?? []) as $key) {
                                        if ((string) $key !== $current) {
                                            $set("../../supplier_links.{$key}.is_main", false);
                                        }
                                    }
                                }),
                        ]),
                ]),
        ]);
    }

    public function beforeFill(array $data): array
    {
        /** @var Product $record */
        $record = $this->caller->getRecord();

        $data['supplier_links'] = ProductSupplier::query()->where('product_id', $record->getKey())->orderBy('id')->get()
            ->map(fn (ProductSupplier $link) => $link->only(['supplier_id', 'reference', 'bag_kg', 'carton_kg', 'carton_price', 'is_main']))
            ->all();

        return $data;
    }

    public function beforeUpdate(array $data, Model $record): array
    {
        self::sync($record->getKey(), Arr::pull($data, 'supplier_links', []));

        return $data;
    }

    /**
     * Remplace les fournisseurs du bonbon par ceux du formulaire. Un seul principal : le premier coché,
     * sinon le premier de la liste.
     *
     * @param  array<int|string, array{supplier_id: int|string|null, reference?: ?string, bag_kg?: int|string|null, carton_kg?: int|string|null, carton_price?: float|string|null, is_main?: bool}>  $rows
     */
    public static function sync(int $productId, array $rows): void
    {
        $rows = collect($rows)->filter(fn (array $row) => filled($row['supplier_id'] ?? null))->unique('supplier_id')->values();
        $mainIndex = $rows->search(fn (array $row) => (bool) ($row['is_main'] ?? false));
        $mainIndex = $mainIndex === false ? 0 : $mainIndex;

        ProductSupplier::query()->where('product_id', $productId)
            ->whereNotIn('supplier_id', $rows->pluck('supplier_id')->map(fn ($id) => (int) $id))
            ->get()->each->delete();

        // Le principal en dernier : c'est lui qui retire le titre aux autres.
        $rows->map(fn (array $row, int $i) => [...$row, 'is_main' => $i === $mainIndex])
            ->sortBy('is_main')
            ->each(fn (array $row) => ProductSupplier::updateOrCreate(
                ['product_id' => $productId, 'supplier_id' => (int) $row['supplier_id']],
                [
                    'reference' => filled($row['reference'] ?? null) ? $row['reference'] : null,
                    'bag_kg' => filled($row['bag_kg'] ?? null) ? (int) $row['bag_kg'] : null,
                    'carton_kg' => filled($row['carton_kg'] ?? null) ? (int) $row['carton_kg'] : null,
                    'carton_price' => filled($row['carton_price'] ?? null) ? (float) $row['carton_price'] : null,
                    'is_main' => $row['is_main'],
                ],
            ));
    }
}
