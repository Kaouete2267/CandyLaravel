<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\StaffPermissions;

/**
 * Le glossaire d'ingrédients (et son menu) est abandonné : la réécriture des ingrédients est désormais
 * entièrement confiée à l'IA (Modules\Ia\Services\IngredientsAiWriter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ingredient_terms');
        StaffPermissions::delete(['confiserie:manage-ingredient-terms']);
    }

    public function down(): void
    {
        Schema::create('ingredient_terms', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->json('aliases');
            $table->json('name');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        StaffPermissions::create(['confiserie:manage-ingredient-terms'], grantToRolesWith: 'confiserie');
    }
};
