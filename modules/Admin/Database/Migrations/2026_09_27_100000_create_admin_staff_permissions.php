<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        // Jusqu'ici ouverts à tout le personnel.
        StaffPermissions::create(['dashboard', 'confiserie']);
        // Jusqu'ici couvertes par « Paramètres de base » (settings:core).
        StaffPermissions::create(['settings:manage-taxes'], grantToRolesWith: 'settings:core');
    }

    public function down(): void
    {
        StaffPermissions::delete(['dashboard', 'confiserie', 'settings:manage-taxes']);
    }
};
