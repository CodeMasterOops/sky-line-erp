<?php

namespace App\Http\Controllers\Api\Admin\UserManagement;

use App\Annotation\Permissions;
use App\Services\TenantService;
use App\Traits\PermissionHelper;
use App\Http\Controllers\Controller;
use App\Services\PermissionRegistry;

class PermissionController extends Controller
{
    use PermissionHelper;

    #[Permissions('list_role')]
    #[Permissions('create_role')]
    #[Permissions('edit_role')]
    public function __invoke()
    {
        return response()->json([
            'data' => $this->visiblePermissions(),
        ]);
    }

    /**
     * The catalogue, minus the permissions of modules this company does not run.
     * Roles keep whatever they were saved with — this only controls what the
     * role editor offers, so a disabled module stops advertising its permissions
     * without rewriting anybody's roles.
     *
     * @return array<string, array<string, list<array{permission: string, description: string}>>>
     */
    private function visiblePermissions(): array
    {
        $permissions = $this->getAllPermissions();
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            return $permissions;
        }

        $allowed = array_flip(app(PermissionRegistry::class)->forCompany((int) $companyId));

        foreach ($permissions as $module => $groups) {
            if (! is_array($groups)) {
                continue;
            }

            foreach ($groups as $group => $entries) {
                // The catalogue is a cached blob; leave anything that is not the
                // expected {permission, description} shape exactly as it is
                // rather than guessing at it.
                if (! is_array($entries)) {
                    continue;
                }

                $kept = array_values(array_filter(
                    $entries,
                    fn ($entry): bool => ! is_array($entry) || ! isset($entry['permission']) || isset($allowed[$entry['permission']]),
                ));

                if ($kept === []) {
                    unset($permissions[$module][$group]);

                    continue;
                }

                $permissions[$module][$group] = $kept;
            }

            if (($permissions[$module] ?? []) === []) {
                unset($permissions[$module]);
            }
        }

        return $permissions;
    }
}
