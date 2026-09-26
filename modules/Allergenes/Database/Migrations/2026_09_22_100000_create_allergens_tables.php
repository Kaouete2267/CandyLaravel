<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->json('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('allergen_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('contains'); // AllergenLevel : contains | may_contain

            $table->primary(['product_id', 'allergen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergen_product');
        Schema::dropIfExists('allergens');
    }
};
