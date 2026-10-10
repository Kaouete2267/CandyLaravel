<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('lunar_product_variants')->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('lunar_staff')->nullOnDelete();
            $table->integer('quantity');                        // en kg, signé
            $table->integer('stock_after');                     // stock du variant après le mouvement
            $table->string('reason', 20);                       // StockReason
            $table->string('input_unit', 10)->nullable();       // kg | carton (saisie d'origine)
            $table->unsignedInteger('input_quantity')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
