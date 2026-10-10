<?php

namespace Modules\Analyse\Support;

use Filament\Tables\Columns\TextColumn;
use Lunar\Admin\Filament\Resources\ProductResource;

/** Colonnes partagées par les tableaux d'analyse (lignes = tableaux avec `sku` et `product_id`). */
final class Columns
{
    /** SKU du bonbon, cliquable vers sa fiche produit (nouvel onglet). */
    public static function sku(): TextColumn
    {
        return TextColumn::make('sku')
            ->label('SKU')
            ->placeholder('—')
            ->searchable()
            ->sortable()
            ->color('primary')
            ->url(fn (array $record) => isset($record['product_id']) ? ProductResource::getUrl('edit', ['record' => $record['product_id']]) : null)
            ->openUrlInNewTab();
    }
}
