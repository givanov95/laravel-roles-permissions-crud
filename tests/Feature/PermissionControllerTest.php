<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\TestCase;
use Spatie\Permission\Models\Permission;

class PermissionControllerTest extends TestCase
{
    public function test_store_redirects_to_index(): void
    {
        $response = $this->post(route('admin.permissions.store'), ['name' => 'view-posts']);

        $response->assertRedirectToRoute('admin.permissions.index');
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The permission has been created.']);
        $this->assertDatabaseHas('permissions', ['name' => 'view-posts']);
    }

    public function test_update_redirects_back_to_edit(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $response = $this->put(route('admin.permissions.update', $permission), ['name' => 'edit-posts']);

        $response->assertRedirectToRoute('admin.permissions.edit', $permission);
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'The permission has been updated.']);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'edit-posts']);
    }

    public function test_destroy_redirects_to_index(): void
    {
        $permission = Permission::create(['name' => 'temp', 'guard_name' => 'web']);

        $response = $this->delete(route('admin.permissions.destroy', $permission));

        $response->assertRedirectToRoute('admin.permissions.index');
        $this->assertModelMissing($permission);
    }

    // ---------------------------------------------------------------- names spatie's wildcards cannot read

    /** @return array<string, array{0: string}> */
    public static function unreadableNames(): array
    {
        return ['trailing dot' => ['reports.'], 'empty part' => ['a..b'], 'empty subpart' => ['a,,b']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unreadableNames')]
    public function test_with_wildcards_a_name_spatie_cannot_read_is_refused(string $name): void
    {
        config(['permission.enable_wildcard_permission' => true]);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);
        $message = 'With wildcard permissions on, no part of the name may be empty (as in "posts." or "posts..edit").';

        $this->post(route('admin.permissions.store'), ['name' => $name])->assertSessionHasErrors(['name' => $message]);
        $this->put(route('admin.permissions.update', $permission), ['name' => $name])->assertSessionHasErrors(['name' => $message]);

        $this->assertDatabaseMissing('permissions', ['name' => $name]);
        $this->assertSame('view-posts', $permission->fresh()->name);
    }

    public function test_with_wildcards_names_spatie_can_read_pass(): void
    {
        config(['permission.enable_wildcard_permission' => true]);
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->post(route('admin.permissions.store'), ['name' => 'posts.*'])->assertSessionHasNoErrors();
        $this->put(route('admin.permissions.update', $permission), ['name' => 'posts.edit,view'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'posts.*']);
        $this->assertSame('posts.edit,view', $permission->fresh()->name);
    }

    public function test_without_wildcards_a_name_with_an_empty_part_is_only_a_name(): void
    {
        $permission = Permission::create(['name' => 'view-posts', 'guard_name' => 'web']);

        $this->post(route('admin.permissions.store'), ['name' => 'reports.'])->assertSessionHasNoErrors();
        $this->put(route('admin.permissions.update', $permission), ['name' => 'a..b'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'reports.']);
        $this->assertSame('a..b', $permission->fresh()->name);
    }
}
