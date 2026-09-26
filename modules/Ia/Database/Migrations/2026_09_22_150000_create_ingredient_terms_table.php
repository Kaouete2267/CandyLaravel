<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Glossaire des ingrédients déjà rencontrés : catégorie et texte final (par langue) associés à un ingrédient,
 * pour que la réécriture assistée par IA (Modules\Ia\Services\IngredientsWriter) classe de façon cohérente
 * et déterministe d'un produit à l'autre, plutôt que de faire confiance à l'IA pour réinventer le classement
 * à chaque fois. Se complète tout seul : un ingrédient jamais vu est ajouté par l'IA (non relu), à valider
 * ensuite dans le menu « Glossaire d'ingrédients ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_terms', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable()->comment('Null = ingrédient isolé, ne forme pas de groupe.');
            $table->json('aliases')->comment('Formes détectées (françaises, normalisées) qui désignent cet ingrédient.');
            $table->json('name')->comment('Texte final par langue, sans le mot de catégorie répété.');
            $table->timestamp('reviewed_at')->nullable()->comment('Null = ajouté automatiquement par l\'IA, pas encore relu.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_terms');
    }
};
