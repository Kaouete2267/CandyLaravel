<?php

namespace Modules\Admin\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Classes, zones et taux de taxe : réservés à la permission `settings:manage-taxes`.
 *
 * Changer la permission des resources Lunar (voir AdminServiceProvider) ne masque que leurs entrées de menu :
 * sous Filament 4, l'accès aux pages passe par la policy du modèle (`viewAny`), pas par la méthode `can()`
 * que surcharge Lunar\Admin\Support\Resources\BaseResource. Sans policy, l'URL resterait ouverte à tous.
 */
class TaxPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('settings:manage-taxes');
    }
}
