<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
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
            $permissionAnnotations = $this->findPermissionAttributes($request);
        } catch (\ReflectionException $e) {
            Log::error('CheckRoleMiddleware: reflection failed — denying request', [
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
            abort(ResponseAlias::HTTP_FORBIDDEN, 'Permission check unavailable.');
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
     * @return list<Permissions>
     *
     * @throws \ReflectionException
     */
    private function findPermissionAttributes(Request $request): array
    {
        $object = $request->route()->getAction();

        if (! isset($object['controller']) || ! is_string($object['controller'])) {
            return [];
        }

        $controllerArr = explode('@', $object['controller']);

        // Invokable (single-action) controllers are registered without an
        // "@method" suffix; their attributes live on __invoke. Without this the
        // #[Permissions] on an invokable controller would be silently ignored.
        $controller = $controllerArr[0];
        $method = $controllerArr[1] ?? '__invoke';

        if (! method_exists($controller, $method)) {
            return [];
        }

        $reflectionMethod = new \ReflectionMethod($controller, $method);
        $attributes = $reflectionMethod->getAttributes(Permissions::class, \ReflectionAttribute::IS_INSTANCEOF);

        return array_map(fn ($attr) => $attr->newInstance(), $attributes);
    }
}
