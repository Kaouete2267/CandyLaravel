<?php

namespace Modules\Allergenes\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AllergenLevel: string implements HasColor, HasLabel
{
    case Contains = 'contains';
    case MayContain = 'may_contain';

    public function getLabel(): string
    {
        return match ($this) {
            self::Contains => 'Contient',
            self::MayContain => 'Peut contenir (traces)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Contains => 'danger',
            self::MayContain => 'warning',
        };
    }
}
