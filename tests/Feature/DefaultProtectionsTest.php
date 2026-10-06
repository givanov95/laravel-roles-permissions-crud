<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The protections an application gets without configuring anything.
 */
class DefaultProtectionsTest extends DefaultsTestCase
{
    // ---------------------------------------------------------------- who gets in

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.roles.index'))->assertRedirect(route('login'));
        $this->get(route('admin.permissions.index'))->assertRedirect(route('login'));
        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertRedirect(route('login'));
        $this->post(route('admin.permissions.store'), ['name' => 'view-posts'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_an_unverified_user_is_sent_to_the_verification_page(): void
    {
        $user = $this->userWith(['view-roles', 'view-permissions'], verified: false);

        $this->actingAs($user)->get(route('admin.roles.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get(route('admin.permissions.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $user = $this->userWith();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->actingAs($user)->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($user)->put(route('admin.roles.update', $role), ['name' => 'writer'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->actingAs($user)->get(route('admin.permissions.index'), $this->inertia())->assertForbidden();
        $this->actingAs($user)->post(route('admin.permissions.store'), ['name' => 'edit-posts'])->assertForbidden();
        $this->actingAs($user)->put(route('admin.permissions.update', $permission), ['name' => 'edit-posts'])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.permissions.destroy', $permission))->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'writer']);
        $this->assertDatabaseMissing('permissions', ['name' => 'edit-posts']);
        $this->assertModelExists($role);
        $this->assertModelExists($permission);
    }

    public function test_each_screen_needs_its_own_permission(): void
    {
        $rolesOnly = $this->userWith(['view-roles', 'manage-roles']);
        $permissionsOnly = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($rolesOnly)->get(route('admin.roles.index'), $this->inertia())->assertOk();
        $this->actingAs($rolesOnly)->get(route('admin.permissions.index'), $this->inertia())->assertForbidden();

        $this->actingAs($permissionsOnly)->get(route('admin.permissions.index'), $this->inertia())->assertOk();
        $this->actingAs($permissionsOnly)->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
    }

    // ---------------------------------------------------------------- validation

    public function test_a_role_needs_a_unique_name_of_at_most_255_characters(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($user)->post(route('admin.roles.store'), [])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.roles.store'), ['name' => str_repeat('a', 255)])->assertSessionHasNoErrors();
    }

    public function test_a_role_keeps_its_own_name_but_cannot_take_another_ones(): void
    {
        $editor = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($user)->put(route('admin.roles.update', $editor), ['name' => 'editor'])->assertSessionHasNoErrors();
        $this->actingAs($user)->put(route('admin.roles.update', $editor), ['name' => 'writer'])->assertSessionHasErrors('name');
        $this->assertSame('editor', $editor->fresh()->name);
    }

    public function test_the_permissions_of_a_role_must_be_existing_ids(): void
    {
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => ['view-posts']])
            ->assertSessionHasErrors('permissions.0');
        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => [999999]])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    public function test_a_permission_needs_a_unique_name_of_at_most_255_characters(): void
    {
        $existing = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($user)->post(route('admin.permissions.store'), [])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.permissions.store'), ['name' => 'view-posts'])->assertSessionHasErrors('name');
        $this->actingAs($user)->post(route('admin.permissions.store'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
        $this->actingAs($user)->put(route('admin.permissions.update', $existing), ['name' => 'view-posts'])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------- protected roles

    public function test_a_protected_role_keeps_its_name_but_its_permissions_can_change(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['super-admin']]);
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles', 'view-posts']);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $role), ['name' => 'renamed', 'permissions' => [$permission->id]])
            ->assertRedirect();

        $this->assertSame('super-admin', $role->fresh()->name);
        $this->assertTrue($role->fresh()->hasPermissionTo('view-posts'));
    }

    public function test_a_protected_role_cannot_be_deleted(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['super-admin']]);
        $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->actingAs($this->userWith(['view-roles', 'manage-roles']))
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertModelExists($role);
    }

    // ---------------------------------------------------------------- protected permissions

    public function test_the_permissions_that_guard_the_screens_cannot_be_deleted(): void
    {
        $guarding = Permission::create(['name' => 'view-roles', 'guard_name' => 'web']);
        $user = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($user)
            ->delete(route('admin.permissions.destroy', $guarding))
            ->assertRedirect(route('admin.permissions.index'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($guarding);
    }

    public function test_the_permissions_that_guard_the_screens_cannot_be_renamed(): void
    {
        $guarding = Permission::create(['name' => 'view-roles', 'guard_name' => 'web']);

        $this->actingAs($this->userWith(['view-permissions', 'manage-permissions']))
            ->put(route('admin.permissions.update', $guarding), ['name' => 'renamed'])
            ->assertRedirect();

        $this->assertSame('view-roles', $guarding->fresh()->name);
    }

    public function test_a_configured_protected_permission_cannot_be_deleted_or_renamed(): void
    {
        config(['roles-permissions-crud.protected_permissions' => ['reports.export']]);
        $protected = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
        $user = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($user)->delete(route('admin.permissions.destroy', $protected))->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($user)->put(route('admin.permissions.update', $protected), ['name' => 'renamed']);

        $this->assertModelExists($protected);
        $this->assertSame('reports.export', $protected->fresh()->name);
    }

    public function test_any_other_permission_can_still_be_renamed_and_deleted(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($user)->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])->assertRedirect();
        $this->assertSame('read-posts', $permission->fresh()->name);

        $this->actingAs($user)->delete(route('admin.permissions.destroy', $permission))->assertRedirect(route('admin.permissions.index'));
        $this->assertModelMissing($permission);
    }

    public function test_the_screens_say_which_permissions_are_protected(): void
    {
        config(['roles-permissions-crud.protected_permissions' => ['reports.export']]);
        Permission::create(['name' => 'view-roles', 'guard_name' => 'web']);
        Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
        $plain = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-permissions', 'manage-permissions']);

        $flags = collect($this->actingAs($user)->getJson(route('admin.permissions.index'), $this->inertia())->assertOk()->json('props.permissions'))
            ->mapWithKeys(fn (array $permission) => [$permission['name'] => $permission['is_protected']])
            ->all();

        $this->assertSame(true, $flags['view-roles']);
        $this->assertSame(true, $flags['reports.export']);
        $this->assertSame(true, $flags['view-permissions']);
        $this->assertSame(false, $flags['view-posts']);

        $this->actingAs($user)
            ->getJson(route('admin.permissions.edit', $plain), $this->inertia())
            ->assertJsonPath('props.permission.is_protected', false);
        $this->actingAs($user)
            ->getJson(route('admin.permissions.edit', Permission::firstWhere('name', 'view-roles')), $this->inertia())
            ->assertJsonPath('props.permission.is_protected', true);
    }

    // ---------------------------------------------------------------- guards

    public function test_a_permission_of_another_guard_is_a_validation_error_not_a_crash(): void
    {
        config(['auth.guards.api' => ['driver' => 'session', 'provider' => 'users']]);
        $other = Permission::create(['name' => 'api-only', 'guard_name' => 'api']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => [$other->id]])
            ->assertSessionHasErrors('permissions.0');

        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $this->actingAs($user)
            ->put(route('admin.roles.update', $role), ['name' => 'writer', 'permissions' => [$other->id]])
            ->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    // ---------------------------------------------------------------- mass assignment

    public function test_a_role_ignores_attributes_it_was_not_asked_for(): void
    {
        $this->actingAs($this->userWith(['view-roles', 'manage-roles']))
            ->post(route('admin.roles.store'), ['name' => 'editor', 'guard_name' => 'api', 'id' => 999999]);

        $role = Role::firstWhere('name', 'editor');

        $this->assertNotNull($role);
        $this->assertSame('web', $role->guard_name);
        $this->assertNotSame(999999, $role->id);
    }

    public function test_a_permission_ignores_attributes_it_was_not_asked_for(): void
    {
        $this->actingAs($this->userWith(['view-permissions', 'manage-permissions']))
            ->post(route('admin.permissions.store'), ['name' => 'view-posts', 'guard_name' => 'api', 'id' => 999999]);

        $permission = Permission::firstWhere('name', 'view-posts');

        $this->assertNotNull($permission);
        $this->assertSame('web', $permission->guard_name);
        $this->assertNotSame(999999, $permission->id);
    }

    // ---------------------------------------------------------------- the permission cache

    public function test_changing_a_role_takes_effect_for_its_users_straight_away(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $editor = $this->userWith()->assignRole($role);

        $this->assertFalse($editor->can('view-posts'), 'Warms the permission cache.');

        $this->actingAs($this->userWith(['view-roles', 'manage-roles', 'view-posts']))
            ->put(route('admin.roles.update', $role), ['name' => 'editor', 'permissions' => [$permission->id]]);

        $this->assertTrue($editor->fresh()->can('view-posts'));
    }
}
