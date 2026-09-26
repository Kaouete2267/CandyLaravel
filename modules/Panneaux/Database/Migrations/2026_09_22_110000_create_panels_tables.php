<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('panel_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['panel_id', 'code']);
        });

        // Un produit occupe au plus un emplacement (bloc + numéro de case) sur un panneau.
        Schema::create('block_product', function (Blueprint $table) {
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained('lunar_products')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot')->nullable();

            $table->primary(['block_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_product');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('panels');
    }
};
