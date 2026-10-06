<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns;

use Closure;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Illuminate\Http\Request;
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

            return $next($request);
        };
    }
}
