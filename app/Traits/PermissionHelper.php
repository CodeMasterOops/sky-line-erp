<?php

namespace App\Traits;

use ReflectionClass;
use Illuminate\Support\Str;
use App\Annotation\Permissions;
use Doctrine\Common\Annotations\AnnotationReader;

trait PermissionHelper
{
    public function getAllPermissions(): array
    {
        $adminPermissions = $this->getPermissions();

        return array_merge_recursive($adminPermissions, []);
    }

    protected function getPermissions(): array
    {
        $path = app_path().'/Http/Controllers/Api/Admin';
        $classPath = 'App\\Http\\Controllers\\Api\\Admin\\';

        return $this->listFiles($path, $classPath);
    }

    protected function listFiles($path, $classPath): array
    {
        $permissions = [];
        $reader = new AnnotationReader;

        $controllerDir = $path;
        if (file_exists($controllerDir)) {
            $files = scandir($controllerDir);
            foreach ($files as $file) {
                if (Str::endsWith($file, '.php')) {
                    [$filename, $ext] = explode('.', $file);

                    if ($ext !== 'php') {
                        continue;
                    }

                    $class = $classPath.$filename;

                    $reflectedClass = new ReflectionClass($class);

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
