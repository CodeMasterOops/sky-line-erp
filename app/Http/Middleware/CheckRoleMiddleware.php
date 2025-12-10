<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class CheckRoleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requiredPermissions = $this->getRequiredPermissions($request);

        if (count($requiredPermissions)) {
            if (hasPermission($requiredPermissions)) {
                return $next($request);
            } else {
                abort(
                    ResponseAlias::HTTP_FORBIDDEN,
                    '403 Forbidden | you are not allowed to access this resource'
                );
            }
        }

        return $next($request);
    }

    protected function getRequiredPermissions($request): array
    {
        $requiredPermissions = [];
        $permissionAnnotations = [];

        try {
            $permissionAnnotations = $this->findPermissionAnnotations($request);
        } catch (\ReflectionException $e) {
        }

        foreach ($permissionAnnotations as $permissionAnnotation) {
            $routeFromAnnotation = $permissionAnnotation->getRoute();

            if (! $routeFromAnnotation or $routeFromAnnotation == $currentRoute = $request->route()->getName()) {
                $requiredPermissions[] = $permissionAnnotation->value;
            }
        }

        return $requiredPermissions;
    }

    /**
     * @throws \ReflectionException
     */
    private function findPermissionAnnotations(Request $request): array
    {
        $reader = new AnnotationReader;
        $object = $request->route()->getAction();
        $controllerArr = explode('@', $object['controller']);
        if (isset($controllerArr[1])) {
            [$controller, $method] = $controllerArr;
            $reflectionClass = new \ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod($method);
            $allAnnotations = $reader->getMethodAnnotations($reflectionMethod);

            return array_filter($allAnnotations, function ($annotation) {
                return $annotation instanceof Permissions;
            });
        }

        return [];
    }
}
