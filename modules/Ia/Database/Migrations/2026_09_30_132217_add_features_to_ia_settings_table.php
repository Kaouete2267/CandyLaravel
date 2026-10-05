<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages propres à chaque fonction IA (température, sections de prompt) saisis dans Réglages IA : seules
 * les valeurs qui diffèrent du défaut du code y sont gardées — voir Modules\Ia\Support\AiFeature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ia_settings', function (Blueprint $table) {
            $table->json('features')->nullable()->after('gemini_model');
        });
    }

    public function down(): void
    {
        Schema::table('ia_settings', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }
};
