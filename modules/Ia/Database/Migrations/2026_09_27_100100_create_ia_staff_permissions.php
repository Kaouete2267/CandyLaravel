<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        // Jusqu'ici ouverts à tout le personnel.
        StaffPermissions::create(['confiserie:create-product-with-ai', 'confiserie:manage-ingredient-terms']);
        // Les réglages IA rejoignent Paramètres : accordés aux rôles qui ont déjà accès à cette section.
        StaffPermissions::create(['settings:manage-ai'], grantToRolesWith: 'settings');
    }

    public function down(): void
    {
        StaffPermissions::delete(['confiserie:create-product-with-ai', 'confiserie:manage-ingredient-terms', 'settings:manage-ai']);
    }
};
