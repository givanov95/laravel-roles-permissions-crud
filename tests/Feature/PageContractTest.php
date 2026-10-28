<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

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
}
