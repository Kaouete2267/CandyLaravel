<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\StaffPermissions;

/**
 * Réglages du stock (ligne unique, id=1) : contenu d'un carton par défaut, quand le fournisseur
 * du bonbon ne précise pas son conditionnement — voir Modules\Stock\Models\StockSetting::current().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('default_carton_kg')->default(3);
            $table->timestamps();
        });

        StaffPermissions::create(['settings:manage-stock'], grantToRolesWith: 'settings');
    }

    public function down(): void
    {
        StaffPermissions::delete(['settings:manage-stock']);
        Schema::dropIfExists('stock_settings');
    }
};
