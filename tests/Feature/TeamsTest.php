<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\Fixtures\User;
use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * With spatie's teams on, the screens work in the current team (`setPermissionsTeamId()`): its own
 * roles and the global ones are listed, another team's are not there, and what every team shares
 * (the global roles, all permissions) is read-only inside a team.
 */
class TeamsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.teams', true);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    private function role(string $name, ?int $team): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web', 'team_id' => $team]);
    }

    /** @return array<int, string> */
    private function listed(): array
    {
        return array_column($this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])->assertOk()->json('props.dataTable.data'), 'name');
    }

    private function permissionsIndex(): TestResponse
    {
        return $this->getJson(route('admin.permissions.index'), ['X-Inertia' => 'true'])->assertOk();
    }

    public function test_a_team_lists_its_own_roles_and_the_global_ones(): void
    {
        $this->role('admin', null);
        $this->role('editor', 1);
        $this->role('reviewer', 2);

        setPermissionsTeamId(1);
        $this->assertSame(['admin', 'editor'], $this->listed());

        // Outside any team: the global roles.
        setPermissionsTeamId(null);
        $this->assertSame(['admin'], $this->listed());
    }

    public function test_a_role_of_another_team_is_not_found(): void
    {
        $other = $this->role('reviewer', 2);
        setPermissionsTeamId(1);

        $this->get(route('admin.roles.edit', $other))->assertNotFound();
        $this->put(route('admin.roles.update', $other), ['name' => 'renamed'])->assertNotFound();
        $this->delete(route('admin.roles.destroy', $other))->assertNotFound();

        $this->assertSame('reviewer', $other->fresh()->name);
    }

    public function test_inside_a_team_a_global_role_is_read_only(): void
    {
        $global = $this->role('admin', null);
        $own = $this->role('editor', 1);
        setPermissionsTeamId(1);

        $this->get(route('admin.roles.edit', $global))->assertForbidden();
        $this->put(route('admin.roles.update', $global), ['name' => 'renamed'])->assertForbidden();
        $this->delete(route('admin.roles.destroy', $global))->assertForbidden();
        $this->assertSame('admin', $global->fresh()->name);

        $this->put(route('admin.roles.update', $own), ['name' => 'writer'])->assertSessionHasNoErrors();
        $this->assertSame('writer', $own->fresh()->name);

        $rows = collect($this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])->json('props.dataTable.data'))->pluck('can_manage', 'name');
        $this->assertFalse($rows['admin']);
        $this->assertTrue($rows['writer']);
    }

    public function test_outside_any_team_a_global_role_can_be_changed(): void
    {
        $global = $this->role('admin', null);
        setPermissionsTeamId(null);

        $this->put(route('admin.roles.update', $global), ['name' => 'administrator'])->assertSessionHasNoErrors();

        $this->assertSame('administrator', $global->fresh()->name);
    }

    public function test_inside_a_team_the_permissions_are_read_only(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        setPermissionsTeamId(1);

        $this->permissionsIndex()
            ->assertJsonPath('props.rolesPermissionsCrud.canCreate', false)
            ->assertJsonPath('props.dataTable.data.0.can_manage', false);

        $this->get(route('admin.permissions.create'))->assertForbidden();
        $this->post(route('admin.permissions.store'), ['name' => 'edit-posts'])->assertForbidden();
        $this->get(route('admin.permissions.edit', $permission))->assertForbidden();
        $this->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])->assertForbidden();
        $this->delete(route('admin.permissions.destroy', $permission))->assertForbidden();

        $this->assertSame('view-posts', $permission->fresh()->name);
        $this->assertDatabaseMissing('permissions', ['name' => 'edit-posts']);

        setPermissionsTeamId(null);

        $this->permissionsIndex()->assertJsonPath('props.rolesPermissionsCrud.canCreate', true);
        $this->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])->assertSessionHasNoErrors();
        $this->assertSame('read-posts', $permission->fresh()->name);
    }

    public function test_a_new_role_belongs_to_the_current_team(): void
    {
        setPermissionsTeamId(1);

        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'editor', 'team_id' => 1]);
    }

    public function test_a_role_name_is_unique_within_the_team_and_the_global_roles(): void
    {
        $this->role('admin', null);
        $this->role('editor', 1);
        $this->role('reviewer', 2);
        setPermissionsTeamId(1);

        // Another team's name is free here, as spatie's own Role::create() sees it.
        $this->post(route('admin.roles.store'), ['name' => 'reviewer'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('roles', ['name' => 'reviewer', 'team_id' => 1]);

        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasErrors('name');
        $this->post(route('admin.roles.store'), ['name' => 'admin'])->assertSessionHasErrors('name');
    }

    public function test_the_users_of_a_role_are_counted_in_the_current_team(): void
    {
        $global = $this->role('admin', null);

        foreach ([[1, 1], [2, 1], [3, 2]] as [$user, $team]) {
            DB::table('model_has_roles')->insert(['role_id' => $global->id, 'model_type' => User::class, 'model_id' => $user, 'team_id' => $team]);
        }

        setPermissionsTeamId(1);
        $this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])->assertJsonPath('props.dataTable.data.0.users_count', 2);

        setPermissionsTeamId(null);
        $this->getJson(route('admin.roles.index'), ['X-Inertia' => 'true'])->assertJsonPath('props.dataTable.data.0.users_count', 3);
    }
}
