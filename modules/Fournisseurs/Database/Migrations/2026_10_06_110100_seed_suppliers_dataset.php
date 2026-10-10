<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Fournisseurs\Models\Supplier;
use Modules\Fournisseurs\Services\SuppliersDataset;

/**
 * Jeu de départ livré avec l'application (production comprise) : 3 fournisseurs et une répartition des bonbons
 * existants, reproductible. Rejouable à la main : `php artisan bonbon:suppliers-dataset --fresh`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Supplier::query()->exists()) {
            app(SuppliersDataset::class)->seed();
        }
    }

    public function down(): void
    {
        // Les fournisseurs sont supprimés avec leurs tables (migration précédente).
    }
};
