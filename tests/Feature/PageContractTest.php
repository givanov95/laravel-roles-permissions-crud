<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * What the pages get from the server: their component names, the route name prefix, and whether the
 * user may add, edit or delete, so the screens do not offer buttons that end in a 403.
 */
class PageContractTest extends DefaultsTestCase
{
    public function test_the_pages_are_named_after_the_page_prefix(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles', 'view-permissions', 'manage-permissions', 'view-posts']);

        $pages = [
            'admin/roles/Index'       => route('admin.roles.index'),
            'admin/roles/Create'      => route('admin.roles.create'),
            'admin/roles/Edit'        => route('admin.roles.edit', $role),
            'admin/permissions/Index' => route('admin.permissions.index'),
            'admin/permissions/Create' => route('admin.permissions.create'),
            'admin/permissions/Edit'  => route('admin.permissions.edit', $permission),
        ];

        foreach ($pages as $component => $url) {
            $this->actingAs($user)->getJson($url, $this->inertia())
                ->assertOk()
                ->assertJsonPath('component', $component)
                ->assertJsonPath('props.rolesPermissionsCrud.routeNamePrefix', 'admin.');
        }

        config(['roles-permissions-crud.page_prefix' => 'Backoffice']);

        $this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())
            ->assertJsonPath('component', 'Backoffice/roles/Index');
    }

    public function test_a_user_who_may_only_look_is_offered_no_changes(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $viewer = $this->userWith(['view-roles', 'view-permissions']);

        $this->actingAs($viewer)->getJson(route('admin.roles.index'), $this->inertia())
            ->assertJsonPath('props.rolesPermissionsCrud.canCreate', false)
            ->assertJsonPath('props.dataTable.data.0.can_manage', false);

        $this->actingAs($viewer)->getJson(route('admin.permissions.index'), $this->inertia())
            ->assertJsonPath('props.rolesPermissionsCrud.canCreate', false)
            ->assertJsonPath('props.dataTable.data.0.can_manage', false);
    }

    public function test_a_user_who_may_only_look_is_given_no_reason_to_refuse_a_delete(): void
    {
        // Deleting is not theirs at all (a 403), so there is no refusal to explain, even for a protected record.
        config(['roles-permissions-crud.protected_roles' => ['editor'], 'roles-permissions-crud.protected_permissions' => ['view-posts']]);
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $viewer = $this->userWith(['view-roles', 'view-permissions']);

        $role = collect($this->actingAs($viewer)->getJson(route('admin.roles.index'), $this->inertia())->json('props.dataTable.data'))
            ->firstWhere('name', 'editor');
        $permission = collect($this->actingAs($viewer)->getJson(route('admin.permissions.index'), $this->inertia())->json('props.dataTable.data'))
            ->firstWhere('name', 'view-posts');

        $this->assertSame([true, false, null], [$role['is_protected'], $role['can_delete'], $role['delete_refusal']]);
        $this->assertSame([true, false, null], [$permission['is_protected'], $permission['can_delete'], $permission['delete_refusal']]);
    }

    public function test_a_user_who_may_manage_is_offered_changes_within_their_reach(): void
    {
        Role::create(['name' => 'boss', 'guard_name' => 'web'])->givePermissionTo(Permission::create(['name' => 'delete-everything', 'guard_name' => 'web']));
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $manager = $this->userWith(['view-roles', 'manage-roles', 'view-permissions', 'manage-permissions']);

        $roles = collect($this->actingAs($manager)->getJson(route('admin.roles.index'), $this->inertia())
            ->assertJsonPath('props.rolesPermissionsCrud.canCreate', true)
            ->json('props.dataTable.data'))->pluck('can_manage', 'name');
        $this->assertFalse($roles['boss']);

        $permissions = collect($this->actingAs($manager)->getJson(route('admin.permissions.index'), $this->inertia())
            ->assertJsonPath('props.rolesPermissionsCrud.canCreate', true)
            ->json('props.dataTable.data'))->pluck('can_manage', 'name');
        $this->assertFalse($permissions['delete-everything']);
        $this->assertTrue($permissions['view-posts']);
    }

    public function test_renaming_a_protected_permission_is_refused_out_loud(): void
    {
        config(['roles-permissions-crud.protected_permissions' => ['reports.export']]);
        $permission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
        $manager = $this->userWith(['view-permissions', 'manage-permissions', 'reports.export']);

        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'renamed'])
            ->assertSessionHasErrors(['name' => 'This permission is protected; its name cannot be changed.']);
        $this->actingAs($manager)->put(route('admin.permissions.update', $permission), ['name' => 'reports.export'])
            ->assertSessionHasNoErrors();

        $this->assertSame('reports.export', $permission->fresh()->name);
    }

    public function test_what_the_user_holds_is_worked_out_once_per_request(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $user = $this->userWith(['view-roles', 'manage-roles']);

        DB::enableQueryLog();
        $this->actingAs($user)->put(route('admin.roles.update', $role), ['name' => 'editor', 'permissions' => []])->assertSessionHasNoErrors();

        $held = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'select "id", "name" from "permissions"'));
        $this->assertCount(1, $held);
    }

    public function test_what_the_user_holds_is_not_remembered_past_a_queue_job(): void
    {
        $user = $this->userWith(['view-roles']);
        $before = \Givanov95\RolesPermissionsCrud\Support\EscalationGuard::held($user);

        $user->givePermissionTo(Permission::findOrCreate('manage-roles', 'web'));
        // What a queue worker (and Octane) does between jobs.
        $this->app->forgetScopedInstances();

        $this->assertCount(count($before) + 1, \Givanov95\RolesPermissionsCrud\Support\EscalationGuard::held($user->fresh()));
    }

    public function test_the_permissions_list_does_not_query_per_row(): void
    {
        $manager = $this->userWith(['view-permissions', 'manage-permissions']);
        $role = Role::create(['name' => 'boss', 'guard_name' => 'web']);

        $queries = function () use ($manager): int {
            // Once to warm up: a load after a change also reloads spatie's permission cache and the
            // user's own roles and permissions, which do not depend on the rows.
            $this->actingAs($manager)->getJson(route('admin.permissions.index'), $this->inertia())->assertOk();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($manager)->getJson(route('admin.permissions.index'), $this->inertia())->assertOk();

            return count(DB::getQueryLog());
        };

        $role->givePermissionTo(Permission::create(['name' => 'p1', 'guard_name' => 'web']));
        $few = $queries();

        foreach (range(2, 8) as $n) {
            $role->givePermissionTo(Permission::create(['name' => "p{$n}", 'guard_name' => 'web']));
        }

        $this->assertSame($few, $queries());
    }

    public function test_the_roles_list_does_not_query_per_own_role(): void
    {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::findOrCreate('view-roles', 'web'), Permission::findOrCreate('manage-roles', 'web'));
        $user = $this->userWith()->assignRole($admin);

        $queries = function () use ($user): int {
            // Once to warm up, as in the permissions list test above.
            $this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->assertOk();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $rows = $this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->assertOk()
                ->json('props.dataTable.data');

            // Every role on the page is the user's, so each one is weighed for a lock-out: the admin role
            // would lock them out, the others only give view-roles, which admin keeps, and are in use.
            $rows = collect($rows)->keyBy('name');
            $this->assertTrue($rows->every(fn (array $row) => $row['can_manage']));
            $this->assertStringStartsWith('You cannot delete this role: you would lose ', $rows['admin']['delete_refusal']);
            $rows->except('admin')->each(fn (array $row) => $this->assertStringStartsWith('This role is assigned', $row['delete_refusal']));

            return count(DB::getQueryLog());
        };

        $few = $queries();

        foreach (range(1, 6) as $n) {
            $role = Role::create(['name' => "own-{$n}", 'guard_name' => 'web']);
            $role->givePermissionTo(Permission::findOrCreate('view-roles', 'web'));
            $user->assignRole($role);
        }

        $this->assertSame($few, $queries());
    }

    public function test_the_delete_refusal_still_takes_the_ids_of_the_users_roles(): void
    {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::findOrCreate('view-roles', 'web'), Permission::findOrCreate('manage-roles', 'web'));
        $spare = Role::create(['name' => 'spare', 'guard_name' => 'web']);
        $user = $this->userWith()->assignRole($admin);

        // The fourth parameter took the ids of the user's roles before it took an OwnAccess.
        $this->assertStringStartsWith(
            'You cannot delete this role: you would lose ',
            (string) EscalationGuard::roleDeleteRefusal($user, $admin, false, collect([$admin->id])),
        );
        $this->assertNull(EscalationGuard::roleDeleteRefusal($user, $spare, false, collect([$admin->id])));
        $this->assertNull(EscalationGuard::roleDeleteRefusal($user, $spare, inUse: false, ownRoleIds: collect([$admin->id])));
    }

    public function test_no_lock_out_while_escalation_is_not_prevented_even_with_own_access(): void
    {
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::findOrCreate('view-roles', 'web'), Permission::findOrCreate('manage-roles', 'web'));
        $user = $this->userWith()->assignRole($admin);
        $own = EscalationGuard::ownAccess($user);

        config(['roles-permissions-crud.prevent_privilege_escalation' => false]);

        $this->assertTrue(EscalationGuard::locksOut($user, $admin, collect(), $own)->isEmpty());
    }

    public function test_the_list_offers_no_delete_that_would_lock_the_user_out(): void
    {
        config(['roles-permissions-crud.allow_delete_in_use' => true]);
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::findOrCreate('view-roles', 'web'), Permission::findOrCreate('manage-roles', 'web'));
        Role::create(['name' => 'spare', 'guard_name' => 'web']);
        $user = $this->userWith()->assignRole($admin);

        $rows = collect($this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())->json('props.dataTable.data'))
            ->keyBy('name');

        // Deleting their own admin role would take manage-roles away from them; the delete says so too.
        // The row says that, not that the role is in use: deletes in use are allowed here.
        $this->assertFalse($rows['admin']['can_delete']);
        $this->assertStringStartsWith('You cannot delete this role: you would lose ', $rows['admin']['delete_refusal']);
        $this->assertStringContainsString('manage-roles', $rows['admin']['delete_refusal']);
        $this->assertTrue($rows['spare']['can_delete']);
        $this->assertNull($rows['spare']['delete_refusal']);
        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))->assertInertiaFlash('toast.type', 'error');
    }

    public function test_the_list_gives_the_reason_the_delete_gives_for_a_role_in_use_that_would_lock_the_user_out(): void
    {
        // Deletes in use are refused, but the delete checks the lock-out first and says that.
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->givePermissionTo(Permission::findOrCreate('view-roles', 'web'), Permission::findOrCreate('manage-roles', 'web'));
        $user = $this->userWith()->assignRole($admin);

        $refusal = $this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())
            ->json('props.dataTable.data.0.delete_refusal');

        $this->assertStringStartsWith('You cannot delete this role: you would lose ', $refusal);
        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))->assertInertiaFlash('toast.message', $refusal);
    }
}
