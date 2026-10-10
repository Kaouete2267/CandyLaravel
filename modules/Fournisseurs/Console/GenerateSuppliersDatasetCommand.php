<?php

namespace Modules\Fournisseurs\Console;

use Illuminate\Console\Command;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Fournisseurs\Services\SuppliersDataset;

class GenerateSuppliersDatasetCommand extends Command
{
    protected $signature = 'bonbon:suppliers-dataset
        {--seed=2026 : Graine du hasard (même graine = même répartition)}
        {--fresh : Supprime d\'abord tous les fournisseurs existants}';

    protected $description = 'Crée le jeu de départ des fournisseurs (3 fournisseurs, bonbons répartis, prix et conditionnements)';

    public function handle(SuppliersDataset $dataset): int
    {
        if (Supplier::query()->exists()) {
            if (! $this->option('fresh')) {
                $this->components->error('Des fournisseurs existent déjà : relancez avec --fresh pour les remplacer.');

                return self::FAILURE;
            }

            if ($this->input->isInteractive() && ! $this->components->confirm('Tous les fournisseurs et leurs liens avec les bonbons vont être supprimés. Continuer ?', false)) {
                return self::FAILURE;
            }

            $dataset->clear();
        }

        $stats = $dataset->seed((int) $this->option('seed'));

        $this->components->twoColumnDetail('Fournisseurs', (string) $stats['suppliers']);
        $this->components->twoColumnDetail('Liens bonbon ↔ fournisseur', (string) $stats['links']);
        $this->components->twoColumnDetail('Bonbons sans fournisseur', (string) $stats['without_supplier']);

        return self::SUCCESS;
    }
}
