<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\ManualRoutesTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The controllers check the permission themselves, so routes registered without the macro (and
 * without a `permission:` middleware) are not open to every logged-in user.
 */
class ControllerAuthorizationTest extends ManualRoutesTestCase
{
    public function test_a_user_without_the_permission_is_forbidden_on_every_action(): void
    {
        $user = $this->userWith();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->actingAs($user)->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->get(route('admin.roles.create'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($user)->get(route('admin.roles.edit', $role), $this->inertia())->assertForbidden();
        $this->actingAs($user)->put(route('admin.roles.update', $role), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->actingAs($user)->get(route('admin.permissions.index'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->get(route('admin.permissions.create'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->post(route('admin.permissions.store'), ['name' => 'edit-posts'])->assertForbidden();
        $this->actingAs($user)->get(route('admin.permissions.edit', $permission), $this->inertia())->assertForbidden();
        $this->actingAs($user)->put(route('admin.permissions.update', $permission), ['name' => 'edit-posts'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.permissions.destroy', $permission))->assertForbidden();

        $this->assertModelExists($role);
        $this->assertModelExists($permission);
        $this->assertDatabaseMissing('roles', ['name' => 'writer']);
    }

    public function test_a_user_with_the_permission_gets_in(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles', 'view-permissions', 'manage-permissions']);

        $this->actingAs($user)->get(route('admin.roles.index'), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.roles.create'), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.roles.edit', $role), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.permissions.index'), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.permissions.edit', $permission), $this->inertia())->assertOk();
    }

    public function test_each_screen_checks_its_own_permission(): void
    {
        $this->actingAs($this->userWith(['view-roles']))->get(route('admin.permissions.index'), $this->inertia())->assertForbidden();
        $this->actingAs($this->userWith(['view-permissions']))->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
    }

    public function test_a_null_permission_leaves_the_check_to_the_application(): void
    {
        config(['roles-permissions-crud.authorize_permissions' => ['roles' => null, 'permissions' => null]]);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user = $this->userWith();

        $this->actingAs($user)->get(route('admin.roles.index'), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.roles.edit', $role), $this->inertia())->assertOk();
        $this->actingAs($user)->get(route('admin.permissions.index'), $this->inertia())->assertOk();
    }
}
