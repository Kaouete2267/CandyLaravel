<?php

namespace Modules\Stock\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Calendrier d'ouverture de la confiserie, utilisé pour générer le jeu de données de stock.
 *
 * Ouverture : jeudi, samedi, dimanche ; mardi et vendredi en plus pendant les vacances scolaires (zone B) ;
 * tous les jours fériés (sauf 25 décembre et 1er janvier, fermés) ; tous les jours en juillet et août.
 * Fermetures : deux fois « du lundi au vendredi de la semaine suivante », en janvier et fin mai / début juin,
 * sans jour férié dans la période.
 *
 * Multiplicateur d'affluence (non cumulable) : ×2 le dimanche, les jours fériés, en juillet et août ;
 * sinon ×0.9 en décembre, janvier et février ; sinon ×1.
 */
class ShopCalendar
{
    public const PEAK = 2.0;

    public const WINTER = 0.9;

    /** @var array<int, array{from: string, to: string}> */
    private array $schoolHolidays;

    /** @var array<int, array<string, true>> jours fériés par année */
    private array $publicHolidays = [];

    /** @var array<int, array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>> fermetures par année */
    private array $closures = [];

    /**
     * @param  int  $seed  graine du choix des fermetures (même graine = mêmes fermetures)
     * @param  array<int, array{name?: string, from: string, to: string}>|null  $schoolHolidays  périodes inclusives (Y-m-d)
     */
    public function __construct(private int $seed = 0, ?array $schoolHolidays = null)
    {
        $this->schoolHolidays = $schoolHolidays
            ?? json_decode(File::get(dirname(__DIR__).'/resources/data/vacances-zone-b.json'), true);
    }

    public function isOpen(CarbonImmutable $day): bool
    {
        return $this->multiplier($day) > 0;
    }

    /**
     * Multiplicateur d'affluence du jour (0 = fermé). Sans $withClosures, ignore les fermetures annuelles
     * (utile sur l'historique, où les vraies dates de fermeture se lisent dans les ventes).
     */
    public function multiplier(CarbonImmutable $day, bool $withClosures = true): float
    {
        if ($day->format('m-d') === '12-25' || $day->format('m-d') === '01-01' || ($withClosures && $this->isClosure($day))) {
            return 0.0;
        }

        $summer = in_array($day->month, [7, 8], true);
        $holiday = $this->isPublicHoliday($day);

        if ($summer || $holiday || $day->isSunday()) {
            return self::PEAK;
        }

        $open = $day->isThursday() || $day->isSaturday()
            || ($this->isSchoolHoliday($day) && ($day->isTuesday() || $day->isFriday()));

        if (! $open) {
            return 0.0;
        }

        return in_array($day->month, [12, 1, 2], true) ? self::WINTER : 1.0;
    }

    public function isPublicHoliday(CarbonImmutable $day): bool
    {
        return isset($this->publicHolidays($day->year)[$day->toDateString()]);
    }

    public function isSchoolHoliday(CarbonImmutable $day): bool
    {
        $date = $day->toDateString();

        foreach ($this->schoolHolidays as $period) {
            if ($date >= $period['from'] && $date <= $period['to']) {
                return true;
            }
        }

        return false;
    }

    public function isClosure(CarbonImmutable $day): bool
    {
        foreach ($this->closures($day->year) as [$from, $to]) {
            if ($day->betweenIncluded($from, $to)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fermetures annuelles : du lundi au vendredi de la semaine suivante, sans jour férié.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function closures(int $year): array
    {
        if (isset($this->closures[$year])) {
            return $this->closures[$year];
        }

        $random = new Randomizer(new Mt19937($this->seed * 10000 + $year));

        return $this->closures[$year] = [
            $this->pickClosure($random, CarbonImmutable::create($year, 1, 5), CarbonImmutable::create($year, 1, 19), CarbonImmutable::create($year, 1, 12)),
            $this->pickClosure($random, CarbonImmutable::create($year, 5, 4), CarbonImmutable::create($year, 6, 15), CarbonImmutable::create($year, 5, 25)),
        ];
    }

    /** @return array<string, true> jours fériés français (Y-m-d) */
    public function publicHolidays(int $year): array
    {
        if (isset($this->publicHolidays[$year])) {
            return $this->publicHolidays[$year];
        }

        $easter = self::easterSunday($year);
        $days = [
            CarbonImmutable::create($year, 1, 1),
            $easter->addDay(),          // lundi de Pâques
            CarbonImmutable::create($year, 5, 1),
            CarbonImmutable::create($year, 5, 8),
            $easter->addDays(39),       // Ascension
            $easter->addDays(50),       // lundi de Pentecôte
            CarbonImmutable::create($year, 7, 14),
            CarbonImmutable::create($year, 8, 15),
            CarbonImmutable::create($year, 11, 1),
            CarbonImmutable::create($year, 11, 11),
            CarbonImmutable::create($year, 12, 25),
        ];

        return $this->publicHolidays[$year] = collect($days)->mapWithKeys(fn (CarbonImmutable $d) => [$d->toDateString() => true])->all();
    }

    /** Dimanche de Pâques (calendrier grégorien, algorithme de Meeus). */
    public static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $h = (19 * $a + $b - intdiv($b, 4) - intdiv(8 * $b + 13, 25) + 15) % 30;
        $l = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $h - $c % 4) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }

    /**
     * Choisit un lundi entre $earliest et $latest dont la fermeture (12 jours) ne contient aucun jour férié,
     * au hasard parmi les deux candidats les plus proches de $around.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function pickClosure(Randomizer $random, CarbonImmutable $earliest, CarbonImmutable $latest, CarbonImmutable $around): array
    {
        $candidates = [];

        for ($monday = $earliest->isMonday() ? $earliest : $earliest->next(CarbonImmutable::MONDAY); $monday->lte($latest); $monday = $monday->addWeek()) {
            $friday = $monday->addDays(11);
            $hasHoliday = collect($this->publicHolidays($monday->year))->keys()
                ->contains(fn (string $date) => $date >= $monday->toDateString() && $date <= $friday->toDateString());

            if (! $hasHoliday) {
                $candidates[] = $monday;
            }
        }

        if ($candidates === []) {
            $candidates[] = $around->isMonday() ? $around : $around->next(CarbonImmutable::MONDAY);
        }

        usort($candidates, fn (CarbonImmutable $x, CarbonImmutable $y) => abs($x->diffInDays($around)) <=> abs($y->diffInDays($around)));
        $monday = $candidates[$random->getInt(0, min(1, count($candidates) - 1))];

        return [$monday, $monday->addDays(11)];
    }
}
