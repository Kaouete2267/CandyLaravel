<?php

namespace Modules\Analyse\Filament\Concerns;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Analyse\Services\ForecastSettings;
use Modules\Analyse\Services\SalesHistory;

/**
 * Réglages de prévision communs aux pages Projection et Commandes : mêmes champs, et mêmes valeurs
 * mémorisées en session d'une page à l'autre.
 */
trait HasForecastFilters
{
    use HasFiltersForm;

    public function getFiltersSessionKey(): string
    {
        return 'analyse_forecast_filters';
    }

    /** Valeurs par défaut, puis réglages mémorisés en session (quelle que soit la page qui les a choisis), puis l'URL. */
    public function mountHasForecastFilters(): void
    {
        $filled = fn (?array $values) => array_filter($values ?? [], fn ($value) => $value !== null);

        $this->filters = [
            ...ForecastSettings::defaults(),
            'horizon' => 6,
            ...$filled(session()->get($this->getFiltersSessionKey())),
            ...$filled($this->filters),
        ];
        $this->getFiltersForm()->fill($this->filters);
    }

    protected function forecastSettings(): ForecastSettings
    {
        return ForecastSettings::fromFilters($this->filters);
    }

    /** @return array<int, Select> */
    protected function forecastFields(): array
    {
        return [
            Select::make('method')
                ->label('Méthode de projection')
                ->options(ForecastSettings::METHODS)
                ->selectablePlaceholder(false),
            Select::make('years')
                ->label('Années moyennées')
                ->options([2 => '2 ans', 3 => '3 ans'])
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get) => $get('method') === ForecastSettings::AVERAGE),
            Select::make('trend_days')
                ->label('Tendance mesurée sur')
                ->options([30 => '30 derniers jours', 60 => '60 derniers jours', 90 => '90 derniers jours', 180 => '6 derniers mois'])
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get) => $get('method') === ForecastSettings::LAST_YEAR_TREND),
            Select::make('trend_cap')
                ->label('Correction maximale')
                ->options([10 => '± 10 %', 25 => '± 25 %', 50 => '± 50 %'])
                ->selectablePlaceholder(false)
                ->visible(fn (Get $get) => $get('method') === ForecastSettings::LAST_YEAR_TREND),
            Select::make('brand')
                ->label('Marque')
                ->placeholder('Toutes les marques')
                ->options(fn () => app(SalesHistory::class)->brands()),
        ];
    }
}
