<?php

namespace Modules\Legacy\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Legacy\Services\LegacyImporter;

class ImportLegacyCommand extends Command
{
    protected $signature = 'bonbon:import-legacy
        {--source= : Fichier source (défaut : les données d\'origine embarquées dans le module)}
        {--logos= : Dossier des logos de marques (défaut : celui du module)}
        {--images= : Dossier des photos de bonbons, nommées « <SKU>-<nom>.jpg » (défaut : celui du module)}
        {--dry-run : Affiche ce qui serait importé sans rien écrire en base}
        {--fresh : Supprime d\'abord les produits déjà importés (SKU BB-…) puis réimporte}
        {--purge-demo : Supprime aussi les 5 produits de démonstration et le panneau de démo}
        {--keep-case : Garde les noms tels quels (sinon les noms tout en majuscules sont adoucis)}
        {--report= : Écrit le détail produit par produit dans un fichier CSV}';

    protected $description = 'Crée le jeu de données de départ à partir des données de l\'ancienne application';

    public function handle(LegacyImporter $importer): int
    {
        $base = dirname(__DIR__).'/resources/data';

        try {
            $plan = $importer->plan(
                $this->option('source') ?: $base.'/donnees-origine.json',
                $this->option('logos') ?: $base.'/logos',
                (bool) $this->option('keep-case'),
                $this->option('images') ?: $base.'/images',
            );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->summarize($plan);

        if ($report = $this->option('report')) {
            $this->writeReport($plan, $report);
            $this->components->info("Détail écrit dans {$report}");
        }

        if ($this->option('dry-run')) {
            $this->components->info('Simulation : rien n\'a été écrit en base.');

            return self::SUCCESS;
        }

        if (($this->option('fresh') || $this->option('purge-demo')) && $this->input->isInteractive()
            && ! $this->components->confirm('Des produits existants vont être supprimés définitivement. Continuer ?', false)) {
            return self::FAILURE;
        }

        $stats = $importer->import(
            $plan,
            ['fresh' => (bool) $this->option('fresh'), 'purge_demo' => (bool) $this->option('purge-demo')],
            fn (string $message) => $this->components->info($message),
        );

        $this->newLine();
        $this->components->twoColumnDetail('Bonbons créés', (string) $stats['created']);
        $this->components->twoColumnDetail('Bonbons ignorés (SKU déjà présent)', (string) $stats['skipped']);
        $this->components->twoColumnDetail('Marques créées / logos ajoutés', "{$stats['brands']} / {$stats['logos']}");
        $this->components->twoColumnDetail('Photos de bonbons ajoutées', (string) $stats['images']);
        $this->components->twoColumnDetail('Panneaux créés', (string) $stats['panels']);
        $this->components->twoColumnDetail('Bonbons placés sur les panneaux (avec DLUO)', (string) $stats['presences']);
        $this->components->twoColumnDetail('Blocs créés (marques automatiques + information)', (string) $stats['blocks']);

        if ($stats['skipped'] > 0) {
            $this->components->warn('Des bonbons existaient déjà : utilisez --fresh pour les recréer.');
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $plan */
    private function summarize(array $plan): void
    {
        $products = collect($plan['products']);

        $this->components->info(sprintf(
            '%d bonbons, %d marques (%d logos), %d types',
            $products->count(), count($plan['brands']), collect($plan['brands'])->whereNotNull('logo')->count(), count($plan['types']),
        ));

        $this->table(
            ['SKU', 'Nom', 'Marque', 'Type', 'Contient', 'Traces'],
            $products->map(fn ($p) => [
                $p['sku'], $p['name'], $p['brand'] ?? '—', $p['type'] ?? '—',
                implode(', ', $p['contains']) ?: '—', implode(', ', $p['may_contain']) ?: '—',
            ])->all(),
        );

        foreach ($plan['panels'] as $panel) {
            $this->components->twoColumnDetail(
                $panel['name'],
                sprintf('%d bonbon(s), %d bloc(s) d\'information, %d colonnes %s',
                    count($panel['presence']), count($panel['info']),
                    $panel['settings']['page']['columnsPerRow'], $panel['settings']['page']['orientation']),
            );
        }

        $this->components->twoColumnDetail('Avec au moins un allergène contenu', (string) $products->filter(fn ($p) => $p['contains'])->count());
        $this->components->twoColumnDetail('Avec des traces', (string) $products->filter(fn ($p) => $p['may_contain'])->count());
        $this->components->twoColumnDetail('Sans marque', (string) $products->whereNull('brand')->count());
        $this->components->twoColumnDetail('Avec photo', (string) $products->filter(fn ($p) => $p['images'])->count());

        $notes = $products->filter(fn ($p) => $p['notes']);
        foreach ($plan['warnings'] as $warning) {
            $this->components->warn($warning);
        }
        foreach ($notes as $p) {
            $this->components->warn("{$p['sku']} {$p['name']} : ".implode(' ; ', $p['notes']));
        }
    }

    /** @param array<string, mixed> $plan */
    private function writeReport(array $plan, string $path): void
    {
        $file = fopen($path, 'w');
        fwrite($file, "\xEF\xBB\xBF");   // BOM : Excel lit correctement l'UTF-8
        fputcsv($file, ['sku', 'ancien_id', 'nom', 'marque', 'type', 'contient', 'traces', 'remarques', 'ingredients'], ';');

        foreach ($plan['products'] as $p) {
            fputcsv($file, [
                $p['sku'], $p['legacy_id'], $p['name'], $p['brand'], $p['type'],
                implode(',', $p['contains']), implode(',', $p['may_contain']), implode(' | ', $p['notes']), $p['ingredients'],
            ], ';');
        }

        fclose($file);
    }
}
