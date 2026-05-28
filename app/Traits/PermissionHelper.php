<?php

namespace App\Traits;

use Illuminate\Support\Str;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\Cache;
use Doctrine\Common\Annotations\AnnotationReader;

trait PermissionHelper
{
    /**
     * Cache key for the full annotation-derived permission map. The map is
     * defined entirely in code (controller annotations), so it is identical
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
        $reader = new AnnotationReader;

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
                        $annotations = $reader->getMethodAnnotations($reflectionMethod);
                        foreach ($annotations as $annotation) {
                            if ($annotation instanceof Permissions and ($annotation->getGroup())) {
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
