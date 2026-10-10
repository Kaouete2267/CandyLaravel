<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('default_carton_kg')->nullable(); // conditionnement habituel du fournisseur
            $table->unsignedSmallInteger('lead_time_days')->nullable();    // délai de livraison
            $table->json('settings')->nullable();                           // paramètres libres (clé => valeur)
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('reference', 100)->nullable();                 // référence chez le fournisseur
            $table->unsignedSmallInteger('carton_kg')->nullable();        // contenu d'un carton (sinon celui du fournisseur)
            $table->decimal('carton_price', 10, 2)->nullable();           // prix d'achat HT d'un carton
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
        });

        StaffPermissions::create(['confiserie:manage-suppliers'], grantToRolesWith: 'confiserie:manage-stock');
    }

    public function down(): void
    {
        StaffPermissions::delete(['confiserie:manage-suppliers']);
        Schema::dropIfExists('product_supplier');
        Schema::dropIfExists('suppliers');
    }
};
