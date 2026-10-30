<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
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

    public function test_the_users_own_roles_are_read_for_the_current_team(): void
    {
        $editor = $this->role('editor', 1);
        $reviewer = $this->role('reviewer', 2);
        config(['roles-permissions-crud.prevent_privilege_escalation' => true]);
        // Only the pivot is read, so the user needs no row of its own (there is no users table here).
        $user = (new User)->forceFill(['id' => 7]);
        $user->exists = true;

        foreach ([[$editor, 1], [$reviewer, 2]] as [$role, $team]) {
            DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $user->id, 'team_id' => $team]);
        }

        // The roles are remembered for the request, but not across a change of team in it.
        setPermissionsTeamId(1);
        $this->assertTrue(EscalationGuard::ownAccess($user)->includes($editor));
        $this->assertFalse(EscalationGuard::ownAccess($user)->includes($reviewer));

        setPermissionsTeamId(2);
        $this->assertTrue(EscalationGuard::ownAccess($user)->includes($reviewer));
        $this->assertFalse(EscalationGuard::ownAccess($user)->includes($editor));
    }

        public function test_a_global_role_name_is_checked_against_every_team(): void
    {
        $this->role('editor', 1);
        $global = $this->role('admin', null);
        setPermissionsTeamId(null);

        // A global role is seen in every team, so it cannot take a team's role name.
        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertSessionHasErrors('name');
        $this->put(route('admin.roles.update', $global), ['name' => 'editor'])->assertSessionHasErrors('name');
    }

    public function test_a_parameter_of_the_applications_own_routes_is_not_taken_for_a_shared_record(): void
    {
        // The application's routes may carry their own models, such as the team.
        $this->app['router']->bind('team', fn (string $id) => new class extends \Illuminate\Database\Eloquent\Model {});
        $this->app['router']->middleware('web')->get('teams/{team}/roles/create', [\Givanov95\RolesPermissionsCrud\Http\Controllers\RoleController::class, 'create']);
        setPermissionsTeamId(1);

        $this->get('teams/1/roles/create', ['X-Inertia' => 'true'])->assertOk();
    }

    public function test_a_team_cannot_make_a_role_with_a_protected_name(): void
    {
        // Such a role would be protected, and would protect its permissions for every team.
        config(['roles-permissions-crud.protected_roles' => ['Owner']]);
        $own = $this->role('editor', 2);
        setPermissionsTeamId(2);

        $this->post(route('admin.roles.store'), ['name' => 'Owner'])->assertSessionHasErrors('name');
        $this->put(route('admin.roles.update', $own), ['name' => 'Owner'])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('roles', ['name' => 'Owner']);
    }

    public function test_a_protected_role_the_application_made_for_a_team_keeps_its_name_on_save(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['Owner']]);
        $owner = $this->role('Owner', 2);
        setPermissionsTeamId(2);

        $this->put(route('admin.roles.update', $owner), ['name' => 'Owner', 'permissions' => []])->assertSessionHasNoErrors();
    }

    public function test_inside_a_team_the_permissions_count_only_what_the_team_sees(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $this->role('admin', null)->givePermissionTo($permission);
        $this->role('editor', 1)->givePermissionTo($permission);
        $this->role('reviewer', 2)->givePermissionTo($permission);

        setPermissionsTeamId(1);
        $this->permissionsIndex()->assertJsonPath('props.dataTable.data.0.roles_count', 2);

        setPermissionsTeamId(null);
        $this->permissionsIndex()->assertJsonPath('props.dataTable.data.0.roles_count', 3);
    }
}
