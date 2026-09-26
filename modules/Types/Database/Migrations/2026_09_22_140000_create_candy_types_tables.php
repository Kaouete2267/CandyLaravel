<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candy_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();      // identifiant technique (acidule, chocolat…)
            $table->json('name');                      // traduit FR/NL/EN
            $table->string('color', 20)->nullable();   // couleur d'affichage (#rrggbb)
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // Un bonbon a au plus un type.
        Schema::create('candy_type_product', function (Blueprint $table) {
            $table->foreignId('candy_type_id')->constrained('candy_types')->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained('lunar_products')->cascadeOnDelete();

            $table->primary(['candy_type_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candy_type_product');
        Schema::dropIfExists('candy_types');
    }
};
