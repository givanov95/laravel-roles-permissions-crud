<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\WildcardPermissionNotImplementsContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The gaps around the escalation checks: renaming or deleting a permission reaches every role that
 * carries it, whoever manages access must not lock themselves out, a role or permission in use is
 * not deleted by accident, and an error does not tell what an id is.
 */
class EscalationGapsTest extends DefaultsTestCase
{
    /** @param  array<int, string>  $permissions */
    private function role(string $name, array $permissions = []): Role
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    private function permissionManager(string ...$extra): User
    {
        return $this->userWith(['view-permissions', 'manage-permissions', ...$extra]);
    }

    // ---------------------------------------------------------------- renaming and deleting a permission

    public function test_a_permission_in_a_role_beyond_the_user_cannot_be_renamed_or_deleted(): void
    {
        $this->role('boss', ['delete-everything']);
        $permission = Permission::findByName('delete-everything');
        $manager = $this->permissionManager();

        $this->actingAs($manager)->get(route('admin.permissions.edit', $permission))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'harmless'])->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $permission))->assertForbidden();

        $this->assertSame('delete-everything', $permission->fresh()->name);
    }

    public function test_a_permission_given_straight_to_another_user_cannot_be_changed_by_a_user_who_does_not_hold_it(): void
    {
        $this->userWith(['delete-everything']);
        $permission = Permission::findByName('delete-everything');
        $manager = $this->permissionManager();

        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'harmless'])->assertForbidden();

        $this->assertSame('delete-everything', $permission->fresh()->name);
    }

    public function test_a_permission_the_user_holds_can_be_deleted_from_roles_beyond_them(): void
    {
        config(['roles-permissions-crud.allow_delete_in_use' => true]);
        $this->role('boss', ['delete-everything', 'view-posts']);
        $permission = Permission::findByName('view-posts');
        $manager = $this->permissionManager('view-posts');

        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $permission))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertModelMissing($permission);
    }

    public function test_a_permission_in_use_is_renamed_only_to_one_the_user_can_use(): void
    {
        // A rename hands the new name to everyone who has the permission.
        $this->role('editor', ['view-posts']);
        $permission = Permission::findByName('view-posts');
        $manager = $this->permissionManager('view-posts');

        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'approve-payouts'])
            ->assertSessionHasErrors(['name' => 'A permission in use can be renamed only to one you hold yourself.']);
        $this->assertSame('view-posts', $permission->fresh()->name);

        Gate::before(fn ($user) => $user->is($manager) ? true : null);

        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'approve-payouts'])
            ->assertSessionHasNoErrors();
        $this->assertSame('approve-payouts', $permission->fresh()->name);
    }

    public function test_a_permission_in_no_role_can_be_renamed_by_a_user_who_does_not_hold_it(): void
    {
        $permission = Permission::findOrCreate('view-posts', 'web');
        $manager = $this->permissionManager();

        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])->assertRedirect();

        $this->assertSame('read-posts', $permission->fresh()->name);
    }

    // ---------------------------------------------------------------- locking yourself out

    public function test_a_user_cannot_take_the_permission_they_manage_with_away_from_their_own_role(): void
    {
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => Permission::where('name', 'view-roles')->pluck('id')->all()])
            ->assertSessionHasErrors(['permissions' => 'You cannot take manage-roles away from your own role, you need them to manage access.']);

        $this->assertTrue($admin->fresh()->hasPermissionTo('manage-roles'));
    }

    public function test_a_user_who_holds_the_permission_elsewhere_can_take_it_away_from_the_role(): void
    {
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith(['manage-roles'])->assignRole($admin);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => Permission::where('name', 'view-roles')->pluck('id')->all()])
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->fresh()->hasPermissionTo('manage-roles'));
    }

    public function test_a_user_cannot_delete_the_role_they_manage_access_with(): void
    {
        config(['roles-permissions-crud.allow_delete_in_use' => true]);
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin);

        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($admin);
    }

    public function test_someone_elses_role_with_the_same_permissions_can_still_be_changed(): void
    {
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $other = $this->role('other-admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $other), ['name' => 'other-admin', 'permissions' => []])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $other->fresh()->permissions);
    }

    // ---------------------------------------------------------------- locking yourself out, with wildcards

    public function test_with_wildcards_a_user_with_star_may_take_the_access_away_from_their_own_role(): void
    {
        config(['permission.enable_wildcard_permission' => true, 'roles-permissions-crud.allow_delete_in_use' => true]);
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith(['*'])->assignRole($admin);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => Permission::where('name', 'view-roles')->pluck('id')->all()])
            ->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->hasPermissionTo('manage-roles'));

        $row = collect($this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->json('props.dataTable.data'))
            ->firstWhere('name', 'admin');
        $this->assertTrue($row['can_delete']);
        $this->assertNull($row['delete_refusal']);

        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))->assertInertiaFlash('toast.type', 'success');
        $this->assertModelMissing($admin);
    }

    public function test_with_wildcards_a_wildcard_through_another_role_keeps_the_access(): void
    {
        config([
            'permission.enable_wildcard_permission' => true,
            'roles-permissions-crud.allow_delete_in_use' => true,
            'roles-permissions-crud.authorize_permissions.roles' => ['view' => 'roles.view', 'manage' => 'roles.manage'],
        ]);
        $admin = $this->role('admin', ['roles.view', 'roles.manage']);
        $user = $this->userWith()->assignRole($admin, $this->role('roles-anything', ['roles.*']));

        $this->assertSame([], EscalationGuard::locksOut($user, $admin, collect())->all());
        $this->assertSame([], EscalationGuard::locksOut($user, $admin, collect(), EscalationGuard::ownAccess($user))->all());
        $this->assertNull(EscalationGuard::roleDeleteRefusal($user, $admin));
    }

    public function test_with_wildcards_a_wildcard_covers_only_the_names_it_matches_by_spatie_rules(): void
    {
        // The delimiter is the dot: roles.* covers roles.manage, not manage-roles.
        config(['permission.enable_wildcard_permission' => true]);
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin, $this->role('roles-anything', ['roles.*']));

        $this->assertSame(['manage-roles', 'view-roles'], EscalationGuard::locksOut($user, $admin, collect())->all());
    }

    public function test_with_wildcards_a_role_with_only_a_wildcard_cannot_be_deleted_by_whoever_it_lets_in(): void
    {
        config(['permission.enable_wildcard_permission' => true, 'roles-permissions-crud.allow_delete_in_use' => true]);
        $admin = $this->role('admin', ['*']);
        $user = $this->userWith()->assignRole($admin);

        $row = collect($this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->json('props.dataTable.data'))
            ->firstWhere('name', 'admin');
        $this->assertFalse($row['can_delete']);
        $this->assertStringStartsWith('You cannot delete this role: you would lose ', $row['delete_refusal']);
        $this->assertStringContainsString('manage-roles', $row['delete_refusal']);

        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))->assertInertiaFlash('toast.message', $row['delete_refusal']);
        $this->assertModelExists($admin);
    }

    public function test_with_wildcards_a_role_may_lose_the_names_its_wildcard_keeps_but_not_the_wildcard(): void
    {
        config(['permission.enable_wildcard_permission' => true]);
        $admin = $this->role('admin', ['*', 'view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin);
        $ids = fn (string ...$names) => Permission::whereIn('name', $names)->pluck('id')->all();

        // Without the wildcard the role keeps only view-roles: the rest of what guards the screens, which
        // the wildcard covered, would be gone.
        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => $ids('view-roles')])
            ->assertSessionHasErrors(['permissions' => 'You cannot take manage-permissions, manage-roles, view-permissions away from your own role, you need them to manage access.']);

        // The wildcard alone still does.
        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => $ids('*')])
            ->assertSessionHasNoErrors();
        $this->assertSame(['*'], $admin->fresh()->permissions->pluck('name')->all());
    }

    public function test_with_wildcards_a_name_spatie_cannot_read_as_a_wildcard_covers_only_itself(): void
    {
        // spatie cannot index `reports.`; the check weighs it as an exact name instead of failing.
        config(['permission.enable_wildcard_permission' => true]);
        $admin = $this->role('admin', ['*', 'view-roles', 'manage-roles', 'reports.']);
        $user = $this->userWith()->assignRole($admin);
        $ids = fn (string ...$names) => Permission::whereIn('name', $names)->pluck('id');

        $this->assertSame([], EscalationGuard::locksOut($user, $admin, $ids('*', 'reports.'))->all());
        $this->assertSame(
            ['manage-permissions', 'manage-roles', 'view-permissions', 'view-roles'],
            EscalationGuard::locksOut($user, $admin, $ids('reports.'))->all(),
        );
    }

    public function test_with_wildcards_a_wildcard_class_that_is_not_one_fails_as_in_spatie(): void
    {
        // Not a quiet fall back to exact names, which would let a role with only `*` be deleted.
        config(['permission.enable_wildcard_permission' => true, 'permission.wildcard_permission' => \stdClass::class]);
        $admin = $this->role('admin', ['*']);
        $user = $this->userWith()->assignRole($admin);

        $this->expectException(WildcardPermissionNotImplementsContract::class);
        EscalationGuard::locksOut($user, $admin, collect());
    }

    public function test_with_wildcards_a_gate_before_still_does_not_keep_the_access(): void
    {
        // The package cannot tell whether the Gate::before lets the user in because of this very role.
        config(['permission.enable_wildcard_permission' => true]);
        Gate::before(fn () => true);
        $admin = $this->role('admin', ['view-roles', 'manage-roles']);
        $user = $this->userWith()->assignRole($admin);

        $this->assertSame(['manage-roles', 'view-roles'], EscalationGuard::locksOut($user, $admin, collect())->all());
    }

    // ---------------------------------------------------------------- deleting what is in use

    public function test_a_role_assigned_to_a_user_is_not_deleted(): void
    {
        $editor = $this->role('editor');
        $this->userWith()->assignRole($editor);
        $manager = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($manager)->delete(route('admin.roles.destroy', $editor))
            ->assertInertiaFlash('toast.type', 'error');
        $this->assertModelExists($editor);

        config(['roles-permissions-crud.allow_delete_in_use' => true]);

        $this->actingAs($manager)->delete(route('admin.roles.destroy', $editor))
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertModelMissing($editor);
    }

    public function test_a_permission_given_to_a_role_or_a_user_is_not_deleted(): void
    {
        $this->role('editor', ['edit-posts']);
        $inRole = Permission::findByName('edit-posts');
        $direct = Permission::findOrCreate('view-posts', 'web');
        $this->userWith(['view-posts']);
        $manager = $this->permissionManager('edit-posts', 'view-posts');

        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $inRole))->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $direct))->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($inRole);
        $this->assertModelExists($direct);
    }

    public function test_a_role_and_a_permission_in_no_use_are_deleted(): void
    {
        $editor = $this->role('editor');
        $permission = Permission::findOrCreate('view-posts', 'web');

        $this->actingAs($this->userWith(['view-roles', 'manage-roles']))->delete(route('admin.roles.destroy', $editor))
            ->assertInertiaFlash('toast.type', 'success');
        $this->actingAs($this->permissionManager())->delete(route('admin.permissions.destroy', $permission))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertModelMissing($editor);
        $this->assertModelMissing($permission);
    }

    // ---------------------------------------------------------------- what the errors tell

    public function test_the_error_for_a_permission_the_user_does_not_hold_does_not_name_it(): void
    {
        $secret = Permission::findOrCreate('secret-reports', 'web');
        $manager = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($manager)
            ->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => [$secret->id]])
            ->assertSessionHasErrors(['permissions' => 'The selected permission is invalid.']);
    }

    // ---------------------------------------------------------------- the form knows before a save

    public function test_the_form_locks_the_name_of_a_permission_in_use_for_a_user_who_cannot_rename_it(): void
    {
        $this->role('editor', ['view-posts']);
        $inUse = Permission::findByName('view-posts');
        // Nobody has it, so it is the manager's to rename.
        $unused = Permission::findOrCreate('export-posts', 'web');
        $manager = $this->permissionManager('view-posts');

        $this->actingAs($manager)->getJson(route('admin.permissions.edit', $inUse), $this->inertia())
            ->assertJsonPath('props.permission.can_rename', false);
        $this->actingAs($manager)->getJson(route('admin.permissions.edit', $unused), $this->inertia())
            ->assertJsonPath('props.permission.can_rename', true);
    }

    public function test_a_super_admin_may_rename_a_permission_in_use_and_the_form_says_so(): void
    {
        $this->role('editor', ['view-posts']);
        $permission = Permission::findByName('view-posts');
        $super = $this->permissionManager();
        Gate::before(fn ($user) => $user->is($super) ? true : null);

        $this->actingAs($super)->getJson(route('admin.permissions.edit', $permission), $this->inertia())
            ->assertJsonPath('props.permission.can_rename', true);
        $this->actingAs($super)->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])
            ->assertSessionHasNoErrors();
        $this->assertSame('read-posts', $permission->fresh()->name);
    }

    public function test_with_wildcards_the_form_leaves_the_name_to_the_save(): void
    {
        // posts.* covers posts.read, a name no permission has yet: the form cannot know in advance.
        config(['permission.enable_wildcard_permission' => true]);
        $this->role('editor', ['posts.view']);
        $permission = Permission::findByName('posts.view');
        $manager = $this->permissionManager('posts.*');

        $this->actingAs($manager)->getJson(route('admin.permissions.edit', $permission), $this->inertia())
            ->assertJsonPath('props.permission.can_rename', true);
        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'posts.read'])
            ->assertSessionHasNoErrors();
        $this->assertSame('posts.read', $permission->fresh()->name);
    }
}
