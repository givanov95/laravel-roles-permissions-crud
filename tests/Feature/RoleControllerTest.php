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
}
