<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns;

use Closure;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Model;
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
            // The same for a role of another team (spatie teams): the current team's and the global ones
            // are there, as in the list.
            //
            // Inside a team, what other teams share (every permission, the global roles) is read-only:
            // changing it would change their access too.
            $manageInTeam = $ability === Authorizer::MANAGE && Models::inTeam();
            abort_if($manageInTeam && $resource === 'permissions', 403);

            foreach ($request->route()?->parameters() ?? [] as $parameter) {
                // Other parameters (a `{team}` of the application's routes, say) are not the package's.
                if (! $parameter instanceof Model || ! ($parameter instanceof Role || $parameter instanceof Permission)) {
                    continue;
                }

                abort_if($parameter->getAttribute('guard_name') !== Models::guard(), 404);
                abort_if(! Models::visibleInTeam($parameter), 404);
                abort_if($manageInTeam && Models::sharedAcrossTeams($parameter), 403);
            }

            return $next($request);
        };
    }
}
