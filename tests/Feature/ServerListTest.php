<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Lists\RoleList;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\User;
use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The lists are paged, searched and sorted on the server (givanov95/laravel-data-table) and send only
 * the fields each row names.
 */
class ServerListTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    /** @param  array<string, mixed>  $query */
    private function roles(array $query = []): TestResponse
    {
        return $this->getJson(route('admin.roles.index', $query), ['X-Inertia' => 'true'])->assertOk();
    }

    /** @param  array<string, mixed>  $query */
    private function permissions(array $query = []): TestResponse
    {
        return $this->getJson(route('admin.permissions.index', $query), ['X-Inertia' => 'true'])->assertOk();
    }

    private function assign(Role $role, string $modelType, int $times): void
    {
        foreach (range(1, $times) as $id) {
            DB::table('model_has_roles')->insert(['role_id' => $role->id, 'model_type' => $modelType, 'model_id' => $id]);
        }
    }

    /** @return array<int, string> */
    private function names(TestResponse $response): array
    {
        return array_column($response->json('props.dataTable.data'), 'name');
    }

    public function test_the_roles_are_sorted_by_name_by_default_and_paged(): void
    {
        config(['data-table.default_per_page' => 2]);

        foreach (['writer', 'admin', 'editor'] as $name) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }

        $first = $this->roles();
        $this->assertSame(['admin', 'editor'], $this->names($first));
        $first->assertJsonPath('props.dataTable.meta.total', 3)
            ->assertJsonPath('props.dataTable.meta.last_page', 2)
            ->assertJsonPath('props.dataTable.state.sort', ['column' => 'name', 'direction' => 'asc']);

        $this->assertSame(['writer'], $this->names($this->roles(['page' => 2])));
    }

    public function test_the_roles_are_searched_by_name(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->assertSame(['editor'], $this->names($this->roles(['filter' => ['global' => 'edi']])));
    }

    public function test_the_roles_are_sorted_by_the_number_of_users_who_have_them(): void
    {
        $few = Role::create(['name' => 'few', 'guard_name' => 'web']);
        $many = Role::create(['name' => 'many', 'guard_name' => 'web']);
        $this->assign($few, User::class, 1);
        $this->assign($many, User::class, 3);

        $response = $this->roles(['ordering' => ['key' => 'users_count', 'direction' => 'desc']]);

        $this->assertSame(['many', 'few'], $this->names($response));
        $response->assertJsonPath('props.dataTable.data.0.users_count', 3);
    }

    public function test_only_users_are_counted_not_other_models_that_have_the_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $this->assign($role, User::class, 2);
        $this->assign($role, 'App\\Models\\Team', 5);

        $this->roles()->assertJsonPath('props.dataTable.data.0.users_count', 2);
    }

    public function test_a_role_row_sends_only_its_own_fields(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'view-posts', 'guard_name' => 'web']));

        $this->assertSame([
            'id'           => $role->id,
            'name'         => 'editor',
            'users_count'  => 0,
            'others_count' => 0,
            'permissions'  => ['view-posts'],
            'is_protected' => false,
            'can_manage'   => true,
            'can_delete'   => true,
        ], $this->roles()->json('props.dataTable.data.0'));
    }

    public function test_the_columns_say_what_can_be_searched_and_sorted(): void
    {
        $this->assertSame(
            [['name', true, true], ['users_count', true, false]],
            array_map(fn (array $c) => [$c['key'], $c['sortable'], $c['searchable']], $this->roles()->json('props.dataTable.columns')),
        );
    }

    public function test_a_sort_by_a_column_that_is_not_sortable_falls_back_to_the_name(): void
    {
        Role::create(['name' => 'b', 'guard_name' => 'web']);
        Role::create(['name' => 'a', 'guard_name' => 'web']);

        $this->assertSame(['a', 'b'], $this->names($this->roles(['ordering' => ['key' => 'guard_name', 'direction' => 'desc']])));
    }

    public function test_the_permissions_are_searched_and_sorted_by_the_number_of_roles(): void
    {
        $rare = Permission::create(['name' => 'view-rare', 'guard_name' => 'web']);
        $common = Permission::create(['name' => 'view-common', 'guard_name' => 'web']);
        Permission::create(['name' => 'edit-posts', 'guard_name' => 'web']);
        Role::create(['name' => 'a', 'guard_name' => 'web'])->givePermissionTo($rare, $common);
        Role::create(['name' => 'b', 'guard_name' => 'web'])->givePermissionTo($common);

        $response = $this->permissions(['filter' => ['global' => 'view'], 'ordering' => ['key' => 'roles_count', 'direction' => 'desc']]);

        $this->assertSame(['view-common', 'view-rare'], $this->names($response));
        $this->assertSame([
            'id'           => $common->id,
            'name'         => 'view-common',
            'roles_count'  => 2,
            'direct_count' => 0,
            'is_protected' => false,
            'can_manage'   => true,
            'can_delete'   => false,
        ], $response->json('props.dataTable.data.0'));
    }

    public function test_a_role_held_only_by_other_models_says_so_and_offers_no_delete(): void
    {
        // As domoupravitel gives roles to tenants: no users, so the list must say what keeps it in use.
        $role = Role::create(['name' => 'tenant', 'guard_name' => 'web']);
        $this->assign($role, 'App\\Models\\Tenant', 2);

        $this->roles()
            ->assertJsonPath('props.dataTable.data.0.users_count', 0)
            ->assertJsonPath('props.dataTable.data.0.others_count', 2)
            ->assertJsonPath('props.dataTable.data.0.can_delete', false);

        // The list matches what a delete does.
        $this->delete(route('admin.roles.destroy', $role))->assertInertiaFlash('toast.type', 'error');

        config(['roles-permissions-crud.allow_delete_in_use' => true]);
        $this->roles()->assertJsonPath('props.dataTable.data.0.can_delete', true);
        $this->delete(route('admin.roles.destroy', $role))->assertInertiaFlash('toast.type', 'success');
    }

    public function test_a_protected_role_or_permission_offers_no_delete(): void
    {
        config(['roles-permissions-crud.protected_roles' => ['admin'], 'roles-permissions-crud.protected_permissions' => ['view-posts']]);
        Role::create(['name' => 'admin', 'guard_name' => 'web']);
        Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->roles()->assertJsonPath('props.dataTable.data.0.can_delete', false);
        $this->permissions()->assertJsonPath('props.dataTable.data.0.can_delete', false);
    }

    public function test_a_permission_given_only_straight_to_someone_says_so_and_offers_no_delete(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        DB::table('model_has_permissions')->insert(['permission_id' => $permission->id, 'model_type' => User::class, 'model_id' => 1]);
        Permission::create(['name' => 'unused', 'guard_name' => 'web']);

        $rows = collect($this->permissions()->json('props.dataTable.data'))->keyBy('name');

        $this->assertSame([0, 1, false], [$rows['view-posts']['roles_count'], $rows['view-posts']['direct_count'], $rows['view-posts']['can_delete']]);
        $this->assertTrue($rows['unused']['can_delete']);
        $this->delete(route('admin.permissions.destroy', $permission))->assertInertiaFlash('toast.type', 'error');
    }

    public function test_a_delete_goes_back_to_the_list_as_the_user_left_it(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $roles = route('admin.roles.index', ['page' => 2, 'filter' => ['global' => 'ed']]);

        $this->from($roles)->delete(route('admin.roles.destroy', $role))->assertRedirect($roles);
        // From anywhere else, the plain list.
        $this->from(route('admin.permissions.edit', $permission))->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.permissions.index'));
    }

    public function test_an_application_can_bind_its_own_list(): void
    {
        Role::create(['name' => 'editor', 'guard_name' => 'web']);

        $this->app->bind(RoleList::class, fn () => new class extends RoleList
        {
            protected function row(Model $model, Request $request): array
            {
                return [...parent::row($model, $request), 'label' => strtoupper((string) $model->getAttribute('name'))];
            }
        });

        $this->roles()->assertJsonPath('props.dataTable.data.0.label', 'EDITOR');
    }
}
