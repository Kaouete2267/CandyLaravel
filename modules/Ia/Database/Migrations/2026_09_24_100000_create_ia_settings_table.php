<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réglages IA modifiables depuis le backoffice (ligne unique, id=1) : jusqu'ici la clé Gemini ne se
 * configurait que dans le .env, invisible depuis l'admin — voir Modules\Ia\Models\IaSetting::current().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ia_settings', function (Blueprint $table) {
            $table->id();
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_settings');
    }
};
