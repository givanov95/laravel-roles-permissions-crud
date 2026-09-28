<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests;

use Givanov95\RolesPermissionsCrud\RolesPermissionsCrudServiceProvider;
use Illuminate\Routing\Router;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PermissionServiceProvider::class,
            InertiaServiceProvider::class,
            RolesPermissionsCrudServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('roles-permissions-crud.middleware', ['web']);
        $app['config']->set('roles-permissions-crud.authorize_permissions', [
            'roles' => null,
            'permissions' => null,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->rolesPermissionsCrud();
    }
}
