<?php

namespace Modules\Stock\Services;

use Carbon\CarbonImmutable;
use Random\Randomizer;

/**
 * Livraisons fournisseur de l'année : la grosse commande préparée en septembre arrive en deux fois
 * (mars et juin), puis deux réassorts (première semaine d'août, septembre pour tenir jusqu'en mars).
 * Une livraison tombe en semaine, hors jour férié et fermeture de la boutique.
 */
class DeliverySchedule
{
    public const ORDER = 'order';

    public const REFILL = 'refill';

    /** @var list<array{month: int, from: int, to: int, kind: string, label: string}> fenêtres de livraison (jours du mois inclus) */
    public const DELIVERIES = [
        ['month' => 3, 'from' => 2, 'to' => 20, 'kind' => self::ORDER, 'label' => 'Commande de septembre %d — livraison 1/2'],
        ['month' => 6, 'from' => 1, 'to' => 25, 'kind' => self::ORDER, 'label' => 'Commande de septembre %d — livraison 2/2'],
        ['month' => 8, 'from' => 1, 'to' => 7, 'kind' => self::REFILL, 'label' => 'Réassort d\'août'],
        ['month' => 9, 'from' => 8, 'to' => 25, 'kind' => self::REFILL, 'label' => 'Réassort de septembre (jusqu\'en mars)'],
    ];

    public function __construct(private ShopCalendar $calendar) {}

    /**
     * Livraisons prévues entre deux dates (incluses). Le jour est tiré au hasard dans sa fenêtre si un
     * générateur est fourni, sinon c'est le jour possible le plus proche du milieu de la fenêtre.
     *
     * @return list<array{date: CarbonImmutable, kind: string, label: string}>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?Randomizer $random = null): array
    {
        $deliveries = [];

        for ($year = $from->year; $year <= $to->year; $year++) {
            foreach (self::DELIVERIES as $window) {
                $day = $this->pickDay($year, $window, $random);

                if ($day->betweenIncluded($from->startOfDay(), $to->endOfDay())) {
                    $deliveries[] = ['date' => $day, 'kind' => $window['kind'], 'label' => sprintf($window['label'], $year - 1)];
                }
            }
        }

        return $deliveries;
    }

    /**
     * Les $count prochaines livraisons strictement après $after.
     *
     * @return list<array{date: CarbonImmutable, kind: string, label: string}>
     */
    public function upcoming(CarbonImmutable $after, int $count): array
    {
        $years = intdiv($count, count(self::DELIVERIES)) + 1;

        return array_slice($this->between($after->addDay()->startOfDay(), $after->addYears($years)->endOfYear()), 0, $count);
    }

    /** @param  array{month: int, from: int, to: int, kind: string, label: string}  $window */
    private function pickDay(int $year, array $window, ?Randomizer $random): CarbonImmutable
    {
        $candidates = [];
        for ($d = $window['from']; $d <= $window['to']; $d++) {
            $day = CarbonImmutable::create($year, $window['month'], $d);
            if ($day->isWeekday() && ! $this->calendar->isPublicHoliday($day) && ! $this->calendar->isClosure($day)) {
                $candidates[] = $day;
            }
        }

        if ($candidates === []) {
            return CarbonImmutable::create($year, $window['month'], $window['from']);
        }

        if ($random) {
            return $candidates[$random->getInt(0, count($candidates) - 1)];
        }

        $middle = intdiv($window['from'] + $window['to'], 2);
        usort($candidates, fn (CarbonImmutable $a, CarbonImmutable $b) => abs($a->day - $middle) <=> abs($b->day - $middle) ?: $a->day <=> $b->day);

        return $candidates[0];
    }
}
