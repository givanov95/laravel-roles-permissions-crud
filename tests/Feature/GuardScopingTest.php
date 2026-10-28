<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The package lists and changes only the roles and permissions of its configured guard
 * (`roles-permissions-crud.guard`); those of another guard are not there for it.
 */
class GuardScopingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
    }

    public function test_the_lists_show_only_the_configured_guard(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'api-client', 'guard_name' => 'api']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        Permission::create(['name' => 'call-api', 'guard_name' => 'api']);

        $this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(1, 'props.roles')
            ->assertJsonPath('props.roles.0.name', 'editor');

        $this->getJson(route('admin.permissions.index'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonCount(1, 'props.permissions')
            ->assertJsonPath('props.permissions.0.name', 'view-posts');
    }

    public function test_a_role_of_another_guard_is_not_found(): void
    {
        $role = Role::create(['name' => 'api-client', 'guard_name' => 'api']);

        $this->get(route('admin.roles.edit', $role))->assertNotFound();
        $this->put(route('admin.roles.update', $role), ['name' => 'renamed'])->assertNotFound();
        $this->delete(route('admin.roles.destroy', $role))->assertNotFound();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'api-client']);
    }

    public function test_a_permission_of_another_guard_is_not_found(): void
    {
        $permission = Permission::create(['name' => 'call-api', 'guard_name' => 'api']);

        $this->get(route('admin.permissions.edit', $permission))->assertNotFound();
        $this->put(route('admin.permissions.update', $permission), ['name' => 'renamed'])->assertNotFound();
        $this->delete(route('admin.permissions.destroy', $permission))->assertNotFound();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'call-api']);
    }

    public function test_saving_a_role_of_another_guard_with_this_guards_permissions_is_not_a_server_error(): void
    {
        // Escalation checks are off in TestCase, so before this was scoped the request reached
        // syncPermissions(), which looks the ids up in the role's guard and throws.
        $role = Role::create(['name' => 'api-client', 'guard_name' => 'api']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->put(route('admin.roles.update', $role), ['name' => 'api-client', 'permissions' => [$permission->id]])
            ->assertNotFound();

        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    }

    public function test_a_name_taken_in_another_guard_can_be_used(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'api']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'api']);

        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasNoErrors();
        $this->post(route('admin.permissions.store'), ['name' => 'view-posts'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'view-posts', 'guard_name' => 'web']);
    }

    public function test_a_rename_to_a_name_taken_in_another_guard_is_accepted(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'api']);
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'api']);
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->put(route('admin.roles.update', $role), ['name' => 'editor'])->assertSessionHasNoErrors();
        $this->put(route('admin.permissions.update', $permission), ['name' => 'edit-posts'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'editor']);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'edit-posts']);
    }

    public function test_a_name_taken_in_the_same_guard_is_still_rejected(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasErrors('name');
        $this->post(route('admin.permissions.store'), ['name' => 'view-posts'])->assertSessionHasErrors('name');
    }
}
