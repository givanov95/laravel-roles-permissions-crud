<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns;

use Closure;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Http\Request;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a controller check its own permission (`authorize_permissions`) on every action, so it
 * does not matter whether the routes came from the macro, which adds a `permission:` middleware,
 * or were registered by hand. A `null` permission leaves the check to the application.
 */
trait AuthorizesCrudAccess
{
    /**
     * Register in the constructor: `$this->middleware(self::crudAccess('roles'))`. The base
     * Illuminate\Routing\Controller has a non-static `middleware()`, so HasMiddleware cannot be used.
     *
     * @param  'roles'|'permissions'  $resource
     */
    protected static function crudAccess(string $resource): Closure
    {
        return static function (Request $request, Closure $next) use ($resource): Response {
            // Listing needs the view permission; opening a form or changing anything needs manage.
            $ability = $request->route()?->getActionMethod() === 'index' ? Authorizer::VIEW : Authorizer::MANAGE;

            abort_unless(Authorizer::allows($request->user(), $resource, $ability), 403);

            // A role or permission of another guard is not this package's to show or change. Checked
            // here, before the form request, so it is a 404 and not a validation error or a 500.
            // Every bound parameter is looked at, so renamed route parameters are covered too.
            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                abort_if(($parameter instanceof Role || $parameter instanceof Permission) && $parameter->guard_name !== Models::guard(), 404);
            }

            return $next($request);
        };
    }
}
