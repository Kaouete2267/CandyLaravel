<?php

namespace Modules\Stock\Console;

use Illuminate\Console\Command;
use Modules\Stock\Services\StockService;

class ClearStockCommand extends Command
{
    protected $signature = 'bonbon:stock-clear
        {--force : Ne demande pas de confirmation}';

    protected $description = 'Efface tout l\'historique de stock et remet le stock de chaque bonbon à zéro';

    public function handle(StockService $stock): int
    {
        if (! $this->option('force') && $this->input->isInteractive()
            && ! $this->components->confirm('Tout l\'historique de stock va être effacé définitivement. Continuer ?', false)) {
            return self::FAILURE;
        }

        $deleted = $stock->reset();
        $this->components->info("{$deleted} mouvement(s) supprimé(s), stock remis à zéro.");

        return self::SUCCESS;
    }
}
