<?php

namespace Modules\Stock\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StockReason: string implements HasColor, HasLabel
{
    case Receipt = 'receipt';        // réception fournisseur
    case Refill = 'refill';          // remise en rayon / panneau
    case Sale = 'sale';              // sortie / vente
    case Loss = 'loss';              // casse, péremption
    case Inventory = 'inventory';    // correction d'inventaire

    public function getLabel(): string
    {
        return match ($this) {
            self::Receipt => 'Réception fournisseur',
            self::Refill => 'Réassort',
            self::Sale => 'Sortie / vente',
            self::Loss => 'Casse / perte',
            self::Inventory => 'Inventaire',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Receipt, self::Refill => 'success',
            self::Sale => 'info',
            self::Loss => 'danger',
            self::Inventory => 'warning',
        };
    }

    /** @return array<int, self> motifs proposés selon le sens du mouvement */
    public static function forDirection(string $direction): array
    {
        return match ($direction) {
            'in' => [self::Receipt, self::Refill],
            'out' => [self::Sale, self::Loss],
            default => [self::Inventory],
        };
    }
}
