<?php

namespace Modules\Support;

use Lunar\Admin\Support\Facades\LunarPanel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions du panneau d'administration propres aux modules, créées par leurs migrations. Lunar les liste
 * d'elles-mêmes dans Paramètres › Personnel › Contrôle d'accès, groupées par préfixe (`confiserie:…` sous
 * `confiserie`, `settings:…` sous `settings`) ; libellés dans lang/vendor/lunarpanel/fr/auth.php.
 */
class StaffPermissions
{
    /**
     * Crée les permissions et les accorde aux rôles existants qui ont déjà `$grantToRolesWith` (à tous les
     * rôles si null), pour que leur mise en place ne retire aucun accès dont le personnel disposait déjà.
     *
     * @param  list<string>  $handles
     */
    public static function create(array $handles, ?string $grantToRolesWith = null): void
    {
        $guard = LunarPanel::getPanel()->getAuthGuard();

        $permissions = collect($handles)->map(fn (string $handle) => Permission::findOrCreate($handle, $guard));

        Role::query()
            ->where('guard_name', $guard)
            ->when($grantToRolesWith, fn ($query) => $query->whereHas(
                'permissions',
                fn ($query) => $query->where('name', $grantToRolesWith),
            ))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $handles
     */
    public static function delete(array $handles): void
    {
        Permission::query()
            ->where('guard_name', LunarPanel::getPanel()->getAuthGuard())
            ->whereIn('name', $handles)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
