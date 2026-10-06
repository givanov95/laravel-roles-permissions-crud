<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests;

use Givanov95\RolesPermissionsCrud\RolesPermissionsCrudServiceProvider;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\User;
use Illuminate\Routing\Router;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\PermissionServiceProvider;

/**
 * Runs the package the way an application gets it: with the default `middleware`
 * (web, auth, verified) and the default `authorize_permissions`, and the `permission`
 * alias registered as the README tells you to. TestCase, by contrast, switches those
 * protections off so the controllers can be tested on their own.
 */
abstract class DefaultsTestCase extends \Orchestra\Testbench\TestCase
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
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        (require __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->aliasMiddleware('permission', PermissionMiddleware::class);

        // Where the `auth` and `verified` middleware send people who are not allowed in.
        $router->get('login', fn () => 'login')->name('login');
        $router->get('email/verify', fn () => 'verify')->name('verification.notice');

        $router->rolesPermissionsCrud();
    }

    /**
     * A user who holds the given permissions directly.
     *
     * @param  array<int, string>  $permissions
     */
    protected function userWith(array $permissions = [], bool $verified = true): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'name' => 'Tester',
            'email' => uniqid('tester', true).'@example.test',
            'password' => 'secret',
            'email_verified_at' => $verified ? now() : null,
        ]);

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        return $user->givePermissionTo($permissions);
    }

    /**
     * A user holding every permission that exists at this point, through a role.
     */
    protected function userHoldingEverything(): User
    {
        $role = Role::findOrCreate('root', 'web');
        $role->syncPermissions(Permission::all());

        return $this->userWith()->assignRole($role);
    }

    /**
     * Inertia answers an X-Inertia request with JSON, so the page can be read without a root view.
     *
     * @return array<string, string>
     */
    protected function inertia(): array
    {
        return ['X-Inertia' => 'true'];
    }
}
