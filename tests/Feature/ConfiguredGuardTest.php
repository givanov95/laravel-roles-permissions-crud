<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * With `guard` set to something other than `web`, the screens belong to that guard and the
 * default guard's roles and permissions are the foreign ones.
 */
class ConfiguredGuardTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('roles-permissions-crud.guard', 'admin');
    }

    public function test_the_lists_show_the_configured_guard_and_not_the_default_one(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'auditor', 'guard_name' => 'admin']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-audit', 'guard_name' => 'admin']);

        $this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])
            ->assertJsonCount(1, 'props.roles')
            ->assertJsonPath('props.roles.0.name', 'auditor');

        $this->getJson(route('admin.permissions.index'), ['X-Inertia' => 'true'])
            ->assertJsonCount(1, 'props.permissions')
            ->assertJsonPath('props.permissions.0.name', 'view-audit');
    }

    public function test_new_roles_and_permissions_get_the_configured_guard(): void
    {
        $permission = Permission::create(['name' => 'view-audit', 'guard_name' => 'admin']);

        $this->post(route('admin.permissions.store'), ['name' => 'export-audit'])->assertSessionHasNoErrors();
        $this->post(route('admin.roles.store'), ['name' => 'auditor', 'permissions' => [$permission->id]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'export-audit', 'guard_name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'auditor', 'guard_name' => 'admin']);
    }

    public function test_a_role_and_a_permission_of_the_default_guard_are_not_found(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->get(route('admin.roles.edit', $role))->assertNotFound();
        $this->delete(route('admin.permissions.destroy', $permission))->assertNotFound();
    }

    public function test_an_empty_guard_falls_back_to_the_default_guard(): void
    {
        config(['roles-permissions-crud.guard' => null]);

        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
    }
}
