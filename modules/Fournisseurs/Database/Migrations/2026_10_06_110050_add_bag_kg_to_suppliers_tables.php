<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le sac (conditionnement de vente, base des sorties de stock) est propre au fournisseur, comme le carton
 * (conditionnement d'achat) : poids habituel chez le fournisseur, et poids pour un bonbon donné.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_bag_kg')->nullable()->after('address');
        });

        Schema::table('product_supplier', function (Blueprint $table) {
            $table->unsignedSmallInteger('bag_kg')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('product_supplier', fn (Blueprint $table) => $table->dropColumn('bag_kg'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('default_bag_kg'));
    }
};
