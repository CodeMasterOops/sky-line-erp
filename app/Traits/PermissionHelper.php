<?php

namespace App\Traits;

use Illuminate\Support\Str;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\Cache;

trait PermissionHelper
{
    /**
     * Cache key for the full annotation-derived permission map. The map is
     * defined entirely in code (controller attributes), so it is identical
     * for every tenant and only changes on deploy. It is invalidated on
     * migrate (see AppServiceProvider) and by a standard deploy cache:clear.
     */
    public const PERMISSION_MAP_CACHE_KEY = 'admin_permission_map';

    public function getAllPermissions($group = null): array
    {
        // Only the default (full) map is cached; scanning ~95 controllers with
        // reflection on every role-management page load is the expensive path.
        if ($group === null) {
            return Cache::rememberForever(
                self::PERMISSION_MAP_CACHE_KEY,
                fn (): array => $this->getPermissions()
            );
        }

        return $this->getPermissions($group);
    }

    protected function getPermissions($group = []): array
    {
        $permissions = [];
        $modules = ['Settings', 'UserManagement', 'Accounting', 'Inventory', 'Purchase', 'Sales', 'HR'];
        $path = app_path().'/Http/Controllers/Api/Admin';
        $classPath = 'App\\Http\\Controllers\\Api\\Admin\\';
        foreach ($modules as $module) {
            $permissions[$module] = $this->listFiles($group, $path.'/'.$module, $classPath.$module.'\\');
        }

        return $permissions;
    }

    protected function listFiles($group, $path, $classPath): array
    {
        $permissions = [];

        $controllerDir = $path;
        $groupPermissions = ($group and $group->permissions) ? $group->permissions : [];
        if (file_exists($controllerDir)) {
            $files = scandir($controllerDir);
            foreach ($files as $file) {
                if (Str::endsWith($file, '.php')) {
                    [$filename, $ext] = explode('.', $file);

                    if ($ext !== 'php') {
                        continue;
                    }

                    $class = $classPath.$filename;

                    $reflectedClass = new \ReflectionClass($class);

                    foreach ($reflectedClass->getMethods() as $reflectionMethod) {
                        $attributes = $reflectionMethod->getAttributes(Permissions::class, \ReflectionAttribute::IS_INSTANCEOF);
                        foreach ($attributes as $attribute) {
                            $annotation = $attribute->newInstance();
                            if ($annotation->getGroup()) {
                                $permission = $annotation->value;
                                $group = Str::headline($annotation->getGroup());
                                $description = $annotation->getDesc()
                                    ?: ucwords(str_replace('_', ' ', $permission));

                                $permissions[$group][] = [
                                    'permission' => $permission,
                                    'description' => $description,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $permissions;
    }
}
