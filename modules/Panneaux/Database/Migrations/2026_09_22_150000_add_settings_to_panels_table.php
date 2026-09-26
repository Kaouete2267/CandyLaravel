<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            // Réglages du rendu imprimé (orientation, colonnes, titre, encarts…), voir Panel::DEFAULT_SETTINGS.
            $table->json('settings')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('panels', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
