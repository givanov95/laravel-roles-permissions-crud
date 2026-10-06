<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Reading and writing are separate permissions: `view-*` lists, `manage-*` changes.
 */
class ViewManageSplitTest extends DefaultsTestCase
{
    public function test_view_roles_lists_roles_but_cannot_write(): void
    {
        $viewer = $this->userWith(['view-roles']);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->actingAs($viewer)->get(route('admin.roles.index'), $this->inertia())->assertOk();

        $this->actingAs($viewer)->get(route('admin.roles.create'), $this->inertia())->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.roles.store'), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.roles.edit', $role), $this->inertia())->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.roles.update', $role), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'writer']);
        $this->assertSame('editor', $role->fresh()->name);
        $this->assertModelExists($role);
    }

    public function test_view_permissions_lists_permissions_but_cannot_write(): void
    {
        $viewer = $this->userWith(['view-permissions']);
        $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);

        $this->actingAs($viewer)->get(route('admin.permissions.index'), $this->inertia())->assertOk();

        $this->actingAs($viewer)->get(route('admin.permissions.create'), $this->inertia())->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.permissions.store'), ['name' => 'reports.view'])->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.permissions.edit', $permission), $this->inertia())->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.permissions.update', $permission), ['name' => 'renamed'])->assertForbidden();
        $this->actingAs($viewer)->delete(route('admin.permissions.destroy', $permission))->assertForbidden();

        $this->assertDatabaseMissing('permissions', ['name' => 'reports.view']);
        $this->assertModelExists($permission);
        $this->assertSame('reports.export', $permission->fresh()->name);
    }

    public function test_manage_without_view_cannot_list(): void
    {
        $this->actingAs($this->userWith(['manage-roles']))->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
        $this->actingAs($this->userWith(['manage-permissions']))->get(route('admin.permissions.index'), $this->inertia())->assertForbidden();
    }

    public function test_view_and_manage_together_write(): void
    {
        $manager = $this->userWith(['view-roles', 'manage-roles', 'view-permissions', 'manage-permissions']);

        $this->actingAs($manager)->post(route('admin.roles.store'), ['name' => 'editor'])->assertRedirect(route('admin.roles.index'));
        $role = Role::firstWhere('name', 'editor');
        $this->actingAs($manager)->get(route('admin.roles.edit', $role), $this->inertia())->assertOk();
        $this->actingAs($manager)->put(route('admin.roles.update', $role), ['name' => 'writer'])->assertRedirect();
        $this->assertSame('writer', $role->fresh()->name);
        $this->actingAs($manager)->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));
        $this->assertModelMissing($role);

        $this->actingAs($manager)->post(route('admin.permissions.store'), ['name' => 'reports.export'])->assertRedirect(route('admin.permissions.index'));
        $permission = Permission::firstWhere('name', 'reports.export');
        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $permission))->assertRedirect(route('admin.permissions.index'));
        $this->assertModelMissing($permission);
    }
}
