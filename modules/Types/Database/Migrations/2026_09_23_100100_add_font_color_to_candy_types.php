<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candy_types', function (Blueprint $table) {
            // Couleur du texte de l'entête des étiquettes de ce type (la couleur de fond est `color`).
            $table->string('font_color', 20)->default('#000000')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('candy_types', function (Blueprint $table) {
            $table->dropColumn('font_color');
        });
    }
};
