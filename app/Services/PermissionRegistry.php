<?php

namespace App\Services;

use App\Models\User;
use App\Traits\PermissionHelper;

class PermissionRegistry
{
    use PermissionHelper;

    /**
     * Flat, de-duplicated list of every permission value defined by controller
     * annotations. This is the single source of truth for which permission
     * strings may be persisted onto a role.
     *
     * @return list<string>
     */
    public function all(): array
    {
        $values = [];
        $catalogue = $this->getAllPermissions();

        array_walk_recursive(
            $catalogue,
            function ($value, $key) use (&$values): void {
                if ($key === 'permission') {
                    $values[] = $value;
                }
            }
        );

        return array_values(array_unique($values));
    }

    /**
     * The permissions the given actor is allowed to assign. Admins may grant
     * anything in the catalogue; every other user may only grant permissions
     * they themselves currently hold ("grant ≤ own"), preventing privilege
     * escalation through role / user management.
     *
     * @return list<string>
     */
    public function grantableFor(User $user): array
    {
        $all = $this->all();

        if ($user->isAdmin()) {
            return $all;
        }

        return array_values(array_intersect($all, userPermissions($user)));
    }

    /**
     * Determine whether the actor may assign the given role, i.e. the role does
     * not grant any permission beyond what the actor is allowed to grant.
     */
    public function canAssignRolePermissions(User $actor, array $rolePermissions): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return empty(array_diff($rolePermissions, $this->grantableFor($actor)));
    }
}
