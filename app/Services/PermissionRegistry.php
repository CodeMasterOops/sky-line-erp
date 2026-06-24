<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionRegistry
{
    /**
     * Cache key for the flat enforced-permission set. Invalidated on migrate /
     * deploy alongside the grouped permission map (see AppServiceProvider).
     */
    public const ENFORCED_PERMISSIONS_CACHE_KEY = 'admin_enforced_permissions';

    /**
     * Flat, de-duplicated list of *every* permission enforced by a controller
     * #[Permissions] attribute anywhere under Api/Admin — not just the grouped
     * role-management catalogue (which only scans a subset of modules). This is
     * the source of truth for which permission strings are real, so role/user
     * validation neither rejects a legitimate permission nor accepts a typo.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return Cache::rememberForever(
            self::ENFORCED_PERMISSIONS_CACHE_KEY,
            fn (): array => $this->scanEnforcedPermissions()
        );
    }

    /**
     * @return list<string>
     */
    protected function scanEnforcedPermissions(): array
    {
        $base = app_path('Http/Controllers/Api/Admin');

        if (! is_dir($base)) {
            return [];
        }

        $values = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match_all("/#\\[Permissions\\(\\s*'([a-z_]+)'/", (string) file_get_contents($file->getPathname()), $matches)) {
                $values = array_merge($values, $matches[1]);
            }
        }

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
