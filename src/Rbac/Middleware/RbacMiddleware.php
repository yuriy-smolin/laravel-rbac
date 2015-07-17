<?php

namespace SmartCrowd\Rbac\Middleware;

use Illuminate\Support\Facades\Auth;
use SmartCrowd\Rbac\Contracts\RbacManager;
use SmartCrowd\Rbac\Facades\Rbac;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RbacMiddleware
{
    /**
     * @var Rbac
     */
    private $manager;

    public function __construct(RbacManager $rbacManager)
    {
        $this->manager = $rbacManager;
    }

    /**
     * Run the request filter.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @param string|null $permission
     * @return mixed
     */
    public function handle($request, \Closure $next, $permission = null)
    {
        $route = $request->route();

        if (empty($permission)) {
            $permissions = $this->resolvePermissions($route);
        } else {
            $permissions = [$permission];
        }

        foreach ($permissions as $permission) {
            if (!Auth::check() || !$this->manager->checkAccess(Auth::user(), $permission, $route->parameters())) {
                throw new AccessDeniedHttpException;
            }
        }

        return $next($request);
    }

    private function resolvePermissions($route)
    {
        $rbacActions     = $this->manager->getActions();
        $rbacControllers = $this->manager->getControllers();

        $action = $route->getAction();

        $actionName  = stripslashes(str_replace($action['namespace'], '', $action['uses']));
        $actionParts = explode('@', $actionName);

        if (isset($rbacActions[$actionName])) {
            $permissionNames = $rbacActions[$actionName];
        } elseif (isset($rbacControllers[$actionParts[0]])) {
            $permissionNames = $rbacControllers[$actionParts[0]] . '.' . $actionParts[1];
        } else {
            $permissionNames = $this->dotStyle($actionName);
        }

        return $permissionNames;
    }

    private function dotStyle($action)
    {
        return str_replace(['@', '\\'], '.', str_replace('controller', '', strtolower($action)));
    }

}