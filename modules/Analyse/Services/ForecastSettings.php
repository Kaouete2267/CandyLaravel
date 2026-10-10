<?php

namespace Modules\Analyse\Services;

/**
 * Paramètres réglables de la prévision, lus dans les filtres des pages Projection et Commandes.
 */
final class ForecastSettings
{
    public const LAST_YEAR = 'last_year';

    public const LAST_YEAR_TREND = 'last_year_trend';

    public const AVERAGE = 'average';

    /** @var array<string, string> */
    public const METHODS = [
        self::LAST_YEAR_TREND => 'Année précédente corrigée de la tendance',
        self::LAST_YEAR => 'Année précédente (N-1) brute',
        self::AVERAGE => 'Moyenne des années passées',
    ];

    /**
     * @param  string  $method  une clé de METHODS
     * @param  int  $years  nombre d'années moyennées (méthode « moyenne »)
     * @param  int  $trendDays  fenêtre récente comparée à la même fenêtre un an plus tôt (méthode « tendance »)
     * @param  float  $trendCap  écart maximal appliqué par la tendance (0.25 = ±25 %)
     * @param  float  $margin  marge ajoutée aux commandes (0.15 = +15 %)
     * @param  float  $safety  stock de sécurité, en écarts-types de la demande jusqu'à la livraison suivante
     */
    public function __construct(
        public readonly string $method = self::LAST_YEAR_TREND,
        public readonly int $years = 2,
        public readonly int $trendDays = 90,
        public readonly float $trendCap = 0.25,
        public readonly float $margin = 0.15,
        public readonly float $safety = 2.0,
    ) {}

    /**
     * Valeurs par défaut des champs de filtre (pourcentages en entiers).
     *
     * @return array{method: string, years: int, trend_days: int, trend_cap: int, margin: int, safety: int}
     */
    public static function defaults(): array
    {
        return ['method' => self::LAST_YEAR_TREND, 'years' => 2, 'trend_days' => 90, 'trend_cap' => 25, 'margin' => 15, 'safety' => 2];
    }

    /** @param  array<string, mixed>|null  $filters */
    public static function fromFilters(?array $filters): self
    {
        $filters = [...self::defaults(), ...array_filter($filters ?? [], fn ($value) => $value !== null && $value !== '')];

        return new self(
            method: array_key_exists($filters['method'], self::METHODS) ? $filters['method'] : self::LAST_YEAR_TREND,
            years: max(1, min(5, (int) $filters['years'])),
            trendDays: max(14, min(365, (int) $filters['trend_days'])),
            trendCap: max(0, min(100, (int) $filters['trend_cap'])) / 100,
            margin: max(0, min(100, (int) $filters['margin'])) / 100,
            safety: max(0, min(4, (float) $filters['safety'])),
        );
    }

    /** Années de référence remontées depuis la date projetée (1 = N-1). */
    public function referenceYears(): int
    {
        return $this->method === self::AVERAGE ? $this->years : 1;
    }

    public function key(): string
    {
        return implode('|', [$this->method, $this->years, $this->trendDays, $this->trendCap, $this->margin, $this->safety]);
    }
}
