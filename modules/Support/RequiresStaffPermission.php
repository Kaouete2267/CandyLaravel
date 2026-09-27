<?php

namespace Modules\Support;

use Filament\Facades\Filament;

/**
 * Réserve une page ou une resource Filament au personnel qui a la permission `static::$permission` (voir
 * StaffPermissions) : entrée de menu masquée et accès direct refusé (403). Le staff admin passe toujours
 * (règle de Lunar, voir Lunar\Admin\LunarPanelProvider::registerPermissionManifest()).
 *
 * La classe qui l'utilise déclare `protected static string $permission`.
 */
trait RequiresStaffPermission
{
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(static::$permission) ?? false;
    }
}
