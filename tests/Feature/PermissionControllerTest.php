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
}
