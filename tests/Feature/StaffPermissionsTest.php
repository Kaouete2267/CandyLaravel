<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\StaffPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_permissions_are_granted_to_every_existing_role_by_default(): void
    {
        $role = Role::create(['name' => 'vendeur', 'guard_name' => 'staff']);

        StaffPermissions::create(['confiserie:test']);

        $this->assertTrue($role->hasPermissionTo('confiserie:test'));
    }

    public function test_new_permissions_are_granted_only_to_roles_holding_the_given_permission(): void
    {
        $withCore = Role::create(['name' => 'gérant', 'guard_name' => 'staff'])->givePermissionTo('settings:core');
        $withoutCore = Role::create(['name' => 'vendeur', 'guard_name' => 'staff']);

        StaffPermissions::create(['settings:test'], grantToRolesWith: 'settings:core');

        $this->assertTrue($withCore->hasPermissionTo('settings:test'));
        $this->assertFalse($withoutCore->hasPermissionTo('settings:test'));
    }

    public function test_deleting_removes_the_permissions(): void
    {
        StaffPermissions::create(['confiserie:test']);

        StaffPermissions::delete(['confiserie:test']);

        $this->assertFalse(Permission::where('name', 'confiserie:test')->exists());
    }
}
