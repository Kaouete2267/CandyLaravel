<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Support\StaffPermissions;

return new class extends Migration
{
    public function up(): void
    {
        // Accordée d'emblée aux rôles qui gèrent déjà le stock.
        StaffPermissions::create(['confiserie:view-analytics'], grantToRolesWith: 'confiserie:manage-stock');
    }

    public function down(): void
    {
        StaffPermissions::delete(['confiserie:view-analytics']);
    }
};
