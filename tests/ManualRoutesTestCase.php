<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests;

use Givanov95\RolesPermissionsCrud\Http\Controllers\PermissionController;
use Givanov95\RolesPermissionsCrud\Http\Controllers\RoleController;
use Illuminate\Routing\Router;

/**
 * An application that registers the routes itself instead of calling the macro, so nothing but
 * `web` and `auth` stands in front of the controllers.
 */
abstract class ManualRoutesTestCase extends DefaultsTestCase
{
    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->get('login', fn () => 'login')->name('login');

        $router->middleware(['web', 'auth'])->prefix('admin')->name('admin.')->group(function () use ($router): void {
            $router->resource('roles', RoleController::class)->except('show');
            $router->resource('permissions', PermissionController::class)->except('show');
        });
    }
}
