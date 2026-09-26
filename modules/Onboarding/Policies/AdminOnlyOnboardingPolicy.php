<?php

namespace Modules\Onboarding\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Restreint l'écriture des parcours d'onboarding (flows, étapes, conditions)
 * au staff admin. Les autres membres du staff gardent l'accès au checklist,
 * aux tours guidés et à la page de progression — seule la resource d'édition
 * leur est masquée (viewAny() à false cache l'entrée de menu).
 */
abstract class AdminOnlyOnboardingPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDelete(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function reorder(Authenticatable $user): bool
    {
        return $this->isAdmin($user);
    }

    public function replicate(Authenticatable $user, Model $record): bool
    {
        return $this->isAdmin($user);
    }

    protected function isAdmin(Authenticatable $user): bool
    {
        return (bool) ($user->admin ?? false);
    }
}
