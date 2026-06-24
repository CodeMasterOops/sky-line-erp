<?php

namespace App\Http\Requests\Concerns;

use Closure;
use App\Models\Role;
use App\Services\PermissionRegistry;

trait RestrictsAssignableRoles
{
    /**
     * Validation rule ensuring a non-admin actor cannot assign a role that
     * grants permissions beyond their own ("grant ≤ own"). This closes the
     * privilege-escalation path where a delegated user-manager assigns a more
     * powerful pre-existing role.
     */
    protected function assignableRolesRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $actor = $this->user('admin');

            if (! $actor || $actor->isAdmin()) {
                return;
            }

            $registry = app(PermissionRegistry::class);

            $roles = Role::query()->whereIn('id', (array) $value)->get();

            foreach ($roles as $role) {
                if (! $registry->canAssignRolePermissions($actor, $role->permissions ?? [])) {
                    $fail('You are not allowed to assign a role that grants permissions beyond your own.');

                    return;
                }
            }
        };
    }
}
