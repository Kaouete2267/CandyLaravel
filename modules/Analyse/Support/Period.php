<?php

namespace Modules\Analyse\Support;

use Carbon\CarbonImmutable;

/**
 * Période analysée (bornes incluses), choisie dans les filtres de la page Ventes, et sa période de comparaison
 * un an plus tôt.
 */
final class Period
{
    /** @var array<string, string> */
    public const PRESETS = [
        'last_12' => '12 derniers mois',
        'this_year' => 'Année en cours',
        'last_year' => 'Année précédente',
        'summer' => 'Dernier été (juillet-août)',
        'custom' => 'Personnalisée',
    ];

    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    /** @param  array<string, mixed>|null  $filters */
    public static function fromFilters(?array $filters, ?CarbonImmutable $today = null): self
    {
        $today ??= CarbonImmutable::today();
        $yesterday = $today->subDay();

        return match ($filters['period'] ?? 'last_12') {
            'this_year' => new self($today->startOfYear(), $yesterday->max($today->startOfYear())),
            'last_year' => new self($today->subYear()->startOfYear(), $today->subYear()->endOfYear()->startOfDay()),
            'summer' => (function () use ($today) {
                $year = $today->month >= 9 ? $today->year : $today->year - 1;

                return new self(CarbonImmutable::create($year, 7, 1), CarbonImmutable::create($year, 8, 31));
            })(),
            'custom' => self::custom($filters, $yesterday),
            default => new self($today->subYear(), $yesterday),
        };
    }

    /** La même période, un an plus tôt. */
    public function previousYear(): self
    {
        return new self($this->from->subYear(), $this->to->subYear());
    }

    public function label(): string
    {
        return $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y');
    }

    /** @param  array<string, mixed>  $filters */
    private static function custom(array $filters, CarbonImmutable $yesterday): self
    {
        $from = filled($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'])->startOfDay() : $yesterday->subYear()->addDay();
        $to = filled($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'])->startOfDay() : $yesterday;

        return $from->lte($to) ? new self($from, $to) : new self($to, $from);
    }
}
