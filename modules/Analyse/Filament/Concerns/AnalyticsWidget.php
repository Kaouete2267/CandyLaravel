<?php

namespace Modules\Analyse\Filament\Concerns;

use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Widget d'une page d'analyse : reçoit les filtres de la page et n'est visible qu'avec la permission d'analyse.
 */
trait AnalyticsWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('confiserie:view-analytics') ?? false;
    }

    protected function brandFilter(): ?string
    {
        return filled($this->pageFilters['brand'] ?? null) ? (string) $this->pageFilters['brand'] : null;
    }
}
