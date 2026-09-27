<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        // Jusqu'ici ouvert à tout le personnel.
        StaffPermissions::create(['confiserie:manage-candy-types']);
    }

    public function down(): void
    {
        StaffPermissions::delete(['confiserie:manage-candy-types']);
    }
};
