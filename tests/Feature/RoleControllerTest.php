<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleControllerTest extends TestCase
{
    public function test_store_redirects_to_index(): void
    {
        $response = $this->post(route('admin.roles.store'), ['name' => 'editor']);

        $response->assertRedirectToRoute('admin.roles.index');
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The role has been created.']);
        $this->assertDatabaseHas('roles', ['name' => 'editor']);
    }

    public function test_create_shares_permissions_as_value_label_options(): void
    {
        $b = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $a = Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);

        $this->getJson(route('admin.roles.create'), ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.permissions', [
                ['value' => $a->id, 'label' => 'edit-posts'],
                ['value' => $b->id, 'label' => 'view-posts'],
            ]);
    }

    public function test_update_redirects_back_to_edit(): void
    {
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $response = $this->put(route('admin.roles.update', $role), [
            'name' => 'senior-writer',
            'permissions' => [(string) $permission->id],
        ]);

        $response->assertRedirectToRoute('admin.roles.edit', $role);
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The role has been updated.']);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'senior-writer']);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_destroy_redirects_to_index(): void
    {
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        $response = $this->delete(route('admin.roles.destroy', $role));

        $response->assertRedirectToRoute('admin.roles.index');
        $this->assertModelMissing($role);
    }

    public function test_destroy_protected_role_redirects_to_index_without_deleting(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['super-admin']]);
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $response = $this->delete(route('admin.roles.destroy', $role));

        $response->assertRedirectToRoute('admin.roles.index');
        $response->assertInertiaFlash('toast.type', 'error');
        $this->assertModelExists($role);
    }

    // ---------------------------------------------------------------- names spatie's wildcards cannot read

    public function test_with_wildcards_a_permission_spatie_cannot_read_is_not_given_to_a_role(): void
    {
        config(['permission.enable_wildcard_permission' => true]);
        // Stored before the check, or while wildcards were off.
        $unreadable = Permission::create(['name' => 'a..b', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $message = 'With wildcard permissions on, a permission whose name has a part that is empty or only spaces cannot be given until it is renamed: a..b.';

        $this->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => [$unreadable->id]])
            ->assertSessionHasErrors(['permissions' => $message]);
        $this->put(route('admin.roles.update', $role), ['name' => 'writer', 'permissions' => [$unreadable->id]])
            ->assertSessionHasErrors(['permissions' => $message]);

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_with_wildcards_a_role_that_already_has_such_a_permission_may_be_saved(): void
    {
        $unreadable = Permission::create(['name' => 'a..b', 'guard_name' => 'web']);
        $posts = Permission::create(['name' => 'posts.*', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $role->givePermissionTo($unreadable);
        config(['permission.enable_wildcard_permission' => true]);

        $this->put(route('admin.roles.update', $role), ['name' => 'writer', 'permissions' => [$unreadable->id, $posts->id]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['a..b', 'posts.*'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_without_wildcards_a_name_with_an_empty_part_may_be_given(): void
    {
        $permission = Permission::create(['name' => 'a..b', 'guard_name' => 'web']);

        $this->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => [$permission->id]])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Role::findByName('editor', 'web')->hasPermissionTo('a..b'));
    }
}
