<?php

namespace Modules\Fournisseurs\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Stock\Services\StockService;

/**
 * Champs « sac » (conditionnement de vente) et « carton » (conditionnement d'achat) d'un bonbon chez un fournisseur.
 * Vides, ils reprennent le conditionnement habituel du fournisseur (indiqué en placeholder), sinon les réglages du stock.
 * Un carton contient un nombre entier de sacs.
 */
final class PackagingFields
{
    /** @param  Closure(Get): ?Supplier  $supplier */
    public static function bag(Closure $supplier): TextInput
    {
        return TextInput::make('bag_kg')
            ->label('Sac (vente)')
            ->helperText('Poids d\'un sac : une sortie de stock déduit un sac entier.')
            ->placeholder(fn (Get $get) => self::inherited($supplier($get)?->default_bag_kg, StockService::defaults()['bag_kg']))
            ->integer()->minValue(1)->suffix('kg')
            ->live(onBlur: true);
    }

    /** @param  Closure(Get): ?Supplier  $supplier */
    public static function carton(Closure $supplier): TextInput
    {
        return TextInput::make('carton_kg')
            ->label('Carton (achat)')
            ->helperText('Contenu d\'un carton commandé.')
            ->placeholder(fn (Get $get) => self::inherited($supplier($get)?->default_carton_kg, StockService::defaults()['carton_kg']))
            ->integer()->minValue(1)->suffix('kg')
            ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get, $supplier) {
                $bag = (int) ($get('bag_kg') ?: $supplier($get)?->default_bag_kg ?: StockService::defaults()['bag_kg']);

                if (filled($value) && $bag > 0 && (int) $value % $bag !== 0) {
                    $fail("Un carton contient un nombre entier de sacs de {$bag} kg.");
                }
            });
    }

    private static function inherited(?int $supplierValue, int $default): string
    {
        return $supplierValue ? "{$supplierValue} (fournisseur)" : "{$default} (par défaut)";
    }
}
