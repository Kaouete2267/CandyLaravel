<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Poids d'un sac par défaut (conditionnement de vente : une sortie de stock se compte en sacs), quand le
 * fournisseur du bonbon ne le précise pas. 1 kg : la conversion appliquée au passage du stock en kg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_bag_kg')->default(1)->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_settings', function (Blueprint $table) {
            $table->dropColumn('default_bag_kg');
        });
    }
};
