<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        // Jusqu'ici ouverts à tout le personnel.
        StaffPermissions::create(['confiserie:manage-stock', 'confiserie:view-stock-history']);
    }

    public function down(): void
    {
        StaffPermissions::delete(['confiserie:manage-stock', 'confiserie:view-stock-history']);
    }
};
