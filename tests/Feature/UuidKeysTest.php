<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\User;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\UuidPermission;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\UuidRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * An application whose role and permission models have another primary key (a UUID in a `uuid` column,
 * as spatie's docs set one up) gets the lists, the forms and the deletes, with escalation prevented.
 */
class UuidKeysTest extends DefaultsTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.models.role', UuidRole::class);
        $app['config']->set('permission.models.permission', UuidPermission::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();

        // spatie's tables with a `uuid` key on roles and permissions, and UUID columns pointing at them.
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('uuid')->primary();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['team_id', 'name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreign('permission_id')->references('uuid')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreign('role_id')->references('uuid')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->uuid('role_id');
            $table->foreign('permission_id')->references('uuid')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('uuid')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
    }

    /** @param  array<int, string>  $permissions */
    private function role(string $name, array $permissions = []): UuidRole
    {
        foreach ($permissions as $permission) {
            UuidPermission::findOrCreate($permission, 'web');
        }

        /** @var UuidRole $role */
        $role = UuidRole::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * A user with the role, as the screens see them: spatie's cache is cleared, as a new request would find it.
     */
    private function userWithRole(UuidRole $role): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->userWith()->assignRole($role);
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function keys(array $names): array
    {
        return UuidPermission::whereIn('name', $names)->pluck('uuid')->all();
    }

    /** @return array<int, string> */
    private function permissionsOf(UuidRole $role): array
    {
        return $role->fresh()->permissions->pluck('name')->sort()->values()->all();
    }

    /** @return array<int, string> */
    private function managePermissions(): array
    {
        return ['view-roles', 'manage-roles', 'view-permissions', 'manage-permissions'];
    }

    public function test_the_lists_show_the_records_by_their_uuid(): void
    {
        $admin = $this->role('admin', $this->managePermissions());
        $editor = $this->role('editor', ['view-posts']);
        $user = $this->userWithRole($admin);
        UuidPermission::findOrCreate('view-posts', 'web');

        $user->givePermissionTo('view-posts');

        $roles = $this->actingAs($user)->getJson(route('admin.roles.index'), $this->inertia())
            ->assertOk()
            ->json('props.dataTable.data');
        $rows = collect($roles)->keyBy('name');

        $this->assertSame($editor->uuid, $rows['editor']['id']);
        $this->assertTrue($rows['editor']['can_manage']);
        $this->assertTrue($rows['editor']['can_delete']);
        // The user's own role: deleting it would take the screens away.
        $this->assertSame($admin->uuid, $rows['admin']['id']);
        $this->assertFalse($rows['admin']['can_delete']);
        $this->assertStringContainsString('manage-roles', (string) $rows['admin']['delete_refusal']);
        $this->assertSame(1, $rows['admin']['users_count']);

        $permissions = $this->actingAs($user)->getJson(route('admin.permissions.index'), $this->inertia())
            ->assertOk()
            ->json('props.dataTable.data');
        $row = collect($permissions)->firstWhere('name', 'view-posts');

        $this->assertSame(UuidPermission::findByName('view-posts')->uuid, $row['id']);
        $this->assertSame(1, $row['roles_count']);
        $this->assertSame(1, $row['direct_count']);
        $this->assertTrue($row['can_manage']);
    }

    public function test_a_role_is_created_with_permissions_given_by_uuid(): void
    {
        $user = $this->userWithRole($this->role('admin', [...$this->managePermissions(), 'view-posts']));

        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'editor', 'permissions' => $this->keys(['view-posts'])])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['view-posts'], $this->permissionsOf(UuidRole::findByName('editor')));
    }

    public function test_the_edit_form_offers_the_permissions_by_uuid(): void
    {
        $user = $this->userWithRole($this->role('admin', [...$this->managePermissions(), 'view-posts']));
        $editor = $this->role('editor', ['view-posts']);
        $key = UuidPermission::findByName('view-posts')->uuid;

        $page = $this->actingAs($user)->getJson(route('admin.roles.edit', $editor), $this->inertia())
            ->assertOk()
            ->assertJsonPath('props.role.id', $editor->uuid)
            ->assertJsonPath('props.role.permissions', [$key])
            ->json('props.permissions');

        $this->assertContains(['value' => $key, 'label' => 'view-posts'], $page);
    }

    public function test_a_role_is_updated_with_permissions_given_by_uuid(): void
    {
        $user = $this->userWithRole($this->role('admin', [...$this->managePermissions(), 'view-posts', 'edit-posts']));
        $editor = $this->role('editor', ['view-posts']);

        // Keeping the name passes the unique rule: the role itself is ignored by its uuid.
        $this->actingAs($user)
            ->put(route('admin.roles.update', $editor), ['name' => 'editor', 'permissions' => $this->keys(['edit-posts'])])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.roles.edit', $editor));

        $this->assertSame(['edit-posts'], $this->permissionsOf($editor));

        $this->actingAs($user)
            ->put(route('admin.roles.update', $editor), ['name' => 'admin', 'permissions' => []])
            ->assertSessionHasErrors('name');
    }

    public function test_the_escalation_checks_weigh_permissions_by_uuid(): void
    {
        $admin = $this->role('admin', $this->managePermissions());
        UuidPermission::findOrCreate('delete-everything', 'web');
        $user = $this->userWithRole($admin);

        // A key that is no UUID fails validation before it reaches the query, which would throw on a
        // native uuid column (PostgreSQL).
        $this->actingAs($user)
            ->post(route('admin.roles.store'), ['name' => 'rogue', 'permissions' => ['abc']])
            ->assertSessionHasErrors('permissions.0');

        // A permission the user does not hold, and a uuid that is no permission.
        foreach ([$this->keys(['delete-everything']), [(string) Str::uuid()]] as $permissions) {
            $this->actingAs($user)
                ->post(route('admin.roles.store'), ['name' => 'rogue', 'permissions' => $permissions])
                ->assertSessionHasErrors('permissions');
        }

        // Taking the screens away from their own role.
        $this->actingAs($user)
            ->put(route('admin.roles.update', $admin), ['name' => 'admin', 'permissions' => $this->keys(['view-roles'])])
            ->assertSessionHasErrors('permissions');

        $this->assertSame(['manage-permissions', 'manage-roles', 'view-permissions', 'view-roles'], $this->permissionsOf($admin));
        $this->assertFalse(UuidRole::where('name', 'rogue')->exists());
    }

    public function test_a_role_beyond_the_user_cannot_be_opened(): void
    {
        $user = $this->userWithRole($this->role('admin', $this->managePermissions()));
        $owner = $this->role('owner', ['delete-everything']);

        $this->actingAs($user)->get(route('admin.roles.edit', $owner))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.roles.destroy', $owner))->assertForbidden();
    }

    public function test_a_role_is_deleted_by_its_uuid(): void
    {
        $admin = $this->role('admin', $this->managePermissions());
        $editor = $this->role('editor');
        $user = $this->userWithRole($admin);

        $this->actingAs($user)->delete(route('admin.roles.destroy', $editor))->assertRedirect();
        $this->assertNull($editor->fresh());

        // Their own role, which would take the screens away, stays.
        $this->actingAs($user)->delete(route('admin.roles.destroy', $admin))->assertRedirect();
        $this->assertNotNull($admin->fresh());
    }

    public function test_a_permission_is_edited_updated_and_deleted_by_its_uuid(): void
    {
        $user = $this->userWithRole($this->role('admin', $this->managePermissions()));
        $permission = UuidPermission::findOrCreate('view-posts', 'web');

        $this->actingAs($user)->getJson(route('admin.permissions.edit', $permission), $this->inertia())
            ->assertOk()
            ->assertJsonPath('props.permission.id', $permission->uuid);

        // Keeping the name passes the unique rule: the permission itself is ignored by its uuid.
        $this->actingAs($user)
            ->put(route('admin.permissions.update', $permission), ['name' => 'view-posts'])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->put(route('admin.permissions.update', $permission), ['name' => 'read-posts'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('read-posts', $permission->fresh()->name);

        $this->actingAs($user)->delete(route('admin.permissions.destroy', $permission))->assertRedirect();
        $this->assertNull($permission->fresh());
    }

    public function test_a_permission_in_use_is_not_deleted(): void
    {
        $user = $this->userWithRole($this->role('admin', $this->managePermissions()));
        $this->role('editor', ['view-posts']);
        $permission = UuidPermission::findByName('view-posts');

        $user->givePermissionTo('view-posts');

        $this->actingAs($user)->delete(route('admin.permissions.destroy', $permission))->assertRedirect();
        $this->assertNotNull($permission->fresh());
    }
}
