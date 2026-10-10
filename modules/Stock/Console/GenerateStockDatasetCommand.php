<?php

namespace Modules\Stock\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Stock\Models\StockMovement;
use Modules\Stock\Services\Dataset\StockDatasetGenerator;
use Modules\Stock\Services\StockService;

class GenerateStockDatasetCommand extends Command
{
    protected $signature = 'bonbon:stock-dataset
        {--years=3 : Nombre d\'années d\'historique}
        {--until= : Dernier jour généré, Y-m-d (défaut : hier)}
        {--seed=2026 : Graine du hasard (même graine = même jeu de données)}
        {--fresh : Efface d\'abord tout le stock existant}';

    protected $description = 'Génère un historique de mouvements de stock réaliste pour tous les bonbons';

    public function handle(StockDatasetGenerator $generator, StockService $stock): int
    {
        $years = max(1, (int) $this->option('years'));
        $until = $this->option('until') ? CarbonImmutable::parse($this->option('until')) : CarbonImmutable::yesterday();
        $from = $until->subYears($years)->addDay();

        if (StockMovement::query()->exists()) {
            if (! $this->option('fresh')) {
                $this->components->error('Des mouvements de stock existent déjà : relancez avec --fresh, ou effacez-les avec bonbon:stock-clear.');

                return self::FAILURE;
            }

            if ($this->input->isInteractive() && ! $this->components->confirm('Tout le stock existant va être effacé. Continuer ?', false)) {
                return self::FAILURE;
            }

            $stock->reset();
        }

        $stats = $generator->generate($from, $until, (int) $this->option('seed'));

        $this->components->twoColumnDetail('Période', "{$from->toDateString()} → {$until->toDateString()}");
        $this->components->twoColumnDetail('Bonbons', (string) $stats['products']);
        $this->components->twoColumnDetail('Mouvements créés', (string) $stats['movements']);
        $this->components->twoColumnDetail('Ventes (lignes / kg)', "{$stats['sales']} / {$stats['sold_kg']}");
        $this->components->twoColumnDetail('Réceptions (lignes / kg)', "{$stats['receipts']} / {$stats['received_kg']}");

        return self::SUCCESS;
    }
}
