<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Whoever may manage roles still cannot hand out more than they hold, change a role that is
 * beyond them, or strip a protected role.
 */
class PrivilegeEscalationTest extends DefaultsTestCase
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

    /** @return array<int, string> */
    private function permissionsOf(Role $role): array
    {
        return $role->fresh()->permissions->pluck('name')->sort()->values()->all();
    }

    /** @param  array<int, string>  $names
     * @return array<int, int> */
    private function ids(array $names): array
    {
        return Permission::whereIn('name', $names)->pluck('id')->all();
    }

    // ---------------------------------------------------------------- handing out permissions

    public function test_a_role_manager_cannot_give_their_own_role_every_permission(): void
    {
        $editor = $this->role('editor', ['view-roles', 'manage-roles']);
        Permission::findOrCreate('delete-everything', 'web');
        $user = $this->userWith()->assignRole($editor);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $editor), ['name' => 'editor', 'permissions' => Permission::pluck('id')->all()])
            ->assertSessionHasErrors('permissions');

        $this->assertSame(['manage-roles', 'view-roles'], $this->permissionsOf($editor));
        $this->assertFalse($user->fresh()->can('delete-everything'));
    }

    public function test_a_role_manager_can_add_a_permission_they_hold_themselves(): void
    {
        $editor = $this->role('editor', ['view-roles', 'manage-roles']);
        Permission::findOrCreate('view-posts', 'web');
        $user = $this->userWith()->assignRole($editor)->givePermissionTo('view-posts');

        $this->actingAs($user)
            ->put(route('admin.roles.update', $editor), ['name' => 'editor', 'permissions' => $this->ids(['view-roles', 'manage-roles', 'view-posts'])])
            ->assertRedirect();

        $this->assertSame(['manage-roles', 'view-posts', 'view-roles'], $this->permissionsOf($editor));
    }

    public function test_a_role_manager_cannot_create_a_role_with_permissions_they_lack(): void
    {
        Permission::findOrCreate('delete-everything', 'web');
        Permission::findOrCreate('view-posts', 'web');
        $user = $this->userWith(['view-roles', 'manage-roles', 'view-posts']);

        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'rogue', 'permissions' => Permission::pluck('id')->all()])
            ->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('roles', ['name' => 'rogue']);

        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'reader', 'permissions' => $this->ids(['view-posts'])])
            ->assertRedirect(route('admin.roles.index'));
        $this->assertSame(['view-posts'], $this->permissionsOf(Role::findByName('reader')));
    }

    // ---------------------------------------------------------------- roles beyond you

    public function test_a_role_manager_cannot_touch_a_role_that_carries_a_permission_they_lack(): void
    {
        $powerful = $this->role('powerful', ['delete-everything']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($user)->get(route('admin.roles.edit', $powerful), $this->inertia())->assertForbidden();
        $this->actingAs($user)->put(route('admin.roles.update', $powerful), ['name' => 'powerful', 'permissions' => []])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.roles.destroy', $powerful))->assertForbidden();

        $this->assertSame(['delete-everything'], $this->permissionsOf($powerful));
        $this->assertModelExists($powerful);
    }

    public function test_the_roles_say_which_of_them_the_user_can_manage(): void
    {
        $this->role('small', ['view-roles']);
        $this->role('powerful', ['delete-everything']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $flags = collect($this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->assertOk()->json('props.roles'))
            ->mapWithKeys(fn (array $role) => [$role['name'] => $role['can_manage']])
            ->all();

        $this->assertTrue($flags['small']);
        $this->assertFalse($flags['powerful']);
    }

    public function test_the_forms_offer_only_the_permissions_the_user_holds(): void
    {
        Permission::findOrCreate('delete-everything', 'web');
        $small = $this->role('small', ['view-roles']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        $labels = fn (array $options) => collect($options)->pluck('label')->sort()->values()->all();

        $create = $this->actingAs($user)->getJson(route('admin.roles.create'), $this->inertia())->assertOk()->json('props.permissions');
        $edit = $this->actingAs($user)->getJson(route('admin.roles.edit', $small), $this->inertia())->assertOk()->json('props.permissions');

        $this->assertSame(['manage-roles', 'view-roles'], $labels($create));
        $this->assertSame(['manage-roles', 'view-roles'], $labels($edit));
    }

    // ---------------------------------------------------------------- protected roles and permissions

    public function test_a_protected_role_cannot_lose_permissions_even_for_a_user_who_holds_them_all(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['super-admin']]);
        $protected = $this->role('super-admin', ['view-roles', 'manage-roles']);
        $root = $this->userHoldingEverything();

        $this->actingAs($root)
            ->put(route('admin.roles.update', $protected), ['name' => 'super-admin', 'permissions' => $this->ids(['view-roles'])])
            ->assertSessionHasErrors('permissions');
        $this->assertSame(['manage-roles', 'view-roles'], $this->permissionsOf($protected));

        Permission::findOrCreate('view-posts', 'web');
        $this->actingAs($this->userHoldingEverything())
            ->put(route('admin.roles.update', $protected), ['name' => 'super-admin', 'permissions' => $this->ids(['view-roles', 'manage-roles', 'view-posts'])])
            ->assertRedirect();
        $this->assertContains('view-posts', $this->permissionsOf($protected));
    }

    public function test_a_permission_held_by_a_protected_role_cannot_be_deleted_or_renamed(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['super-admin']]);
        $this->role('super-admin', ['reports.export']);
        $permission = Permission::findByName('reports.export');
        $manager = $this->userWith(['view-permissions', 'manage-permissions']);

        $this->actingAs($manager)->delete(route('admin.permissions.destroy', $permission))->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'renamed']);

        $this->assertModelExists($permission);
        $this->assertSame('reports.export', $permission->fresh()->name);

        $flags = collect($this->actingAs($manager)->getJson(route('admin.permissions.index'), $this->inertia())->json('props.permissions'))
            ->mapWithKeys(fn (array $p) => [$p['name'] => $p['is_protected']])
            ->all();
        $this->assertTrue($flags['reports.export']);
    }

    // ---------------------------------------------------------------- a Gate::before super admin

    public function test_a_user_let_through_by_a_gate_before_holds_everything(): void
    {
        $powerful = $this->role('powerful', ['delete-everything']);
        $super = $this->userWith();
        Gate::before(fn ($user) => $user->is($super) ? true : null);

        $this->actingAs($super)->get(route('admin.roles.edit', $powerful), $this->inertia())->assertOk();
        $this->actingAs($super)
            ->put(route('admin.roles.update', $powerful), ['name' => 'powerful', 'permissions' => Permission::pluck('id')->all()])
            ->assertRedirect();
        $this->actingAs($super)->delete(route('admin.roles.destroy', $powerful))->assertRedirect(route('admin.roles.index'));
        $this->assertModelMissing($powerful);

        $offered = $this->actingAs($super)->getJson(route('admin.roles.create'), $this->inertia())->assertOk()->json('props.permissions');
        $this->assertCount(Permission::count(), $offered);
    }

    // ---------------------------------------------------------------- the switch

    public function test_the_protection_can_be_switched_off(): void
    {
        config(['roles-permissions-crud.prevent_privilege_escalation' => false]);
        $editor = $this->role('editor', ['view-roles', 'manage-roles']);
        $powerful = $this->role('powerful', ['delete-everything']);
        $user = $this->userWith()->assignRole($editor);

        $this->actingAs($user)
            ->put(route('admin.roles.update', $editor), ['name' => 'editor', 'permissions' => Permission::pluck('id')->all()])
            ->assertRedirect();
        $this->assertContains('delete-everything', $this->permissionsOf($editor));

        $this->actingAs($user)->get(route('admin.roles.edit', $powerful), $this->inertia())->assertOk();
        $this->actingAs($user)->delete(route('admin.roles.destroy', $powerful))->assertRedirect(route('admin.roles.index'));
        $this->assertModelMissing($powerful);
    }
}
