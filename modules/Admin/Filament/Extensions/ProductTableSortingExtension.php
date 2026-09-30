<?php

namespace Modules\Admin\Filament\Extensions;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Admin\Support\Extending\ResourceExtension;
use Lunar\Models\ProductVariant;

/**
 * Rend triables les colonnes de la liste des produits Lunar (sauf la miniature) et ajoute l'ID et la date
 * d'ajout, triés par défaut du plus récent au plus ancien.
 */
class ProductTableSortingExtension extends ResourceExtension
{
    public function extendTable(Table $table): Table
    {
        $table->getColumn('status')?->sortable();
        $table->getColumn('brand.name')?->sortable();
        $table->getColumn('variants_sum_stock')?->sortable();
        $table->getColumn('productType.name')?->sortable();

        // Nom traduit, stocké en JSON dans attribute_data : trié sur la langue affichée.
        $table->getColumn('attribute_data.name')?->sortable(query: fn (Builder $query, string $direction): Builder => $query
            ->orderBy('attribute_data->name->value->'.app()->getLocale(), $direction));

        // Plusieurs variantes possibles : trié sur la plus petite référence du produit.
        $table->getColumn('variants.sku')?->sortable(query: fn (Builder $query, string $direction): Builder => $query
            ->orderBy(
                ProductVariant::query()
                    ->selectRaw('min(sku)')
                    ->whereColumn('product_id', $query->getModel()->getQualifiedKeyName()),
                $direction,
            ));

        return $table
            ->pushColumns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
