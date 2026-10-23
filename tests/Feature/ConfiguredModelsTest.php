<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\Fixtures\CustomPermission;
use Givanov95\RolesPermissionsCrud\Tests\Fixtures\CustomRole;
use Givanov95\RolesPermissionsCrud\Tests\TestCase;

/**
 * An application that extends spatie's models gets its own classes on every write, so their events,
 * observers and overridden methods run.
 */
class ConfiguredModelsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.models.role', CustomRole::class);
        $app['config']->set('permission.models.permission', CustomPermission::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        CustomRole::$events = [];
        CustomRole::$failSync = false;
        CustomPermission::$events = [];
    }

    public function test_role_writes_go_through_the_configured_model(): void
    {
        $this->post(route('admin.roles.store'), ['name' => 'editor'])->assertRedirect();

        $role = CustomRole::findByName('editor');
        $this->put(route('admin.roles.update', $role), ['name' => 'senior-editor'])->assertRedirect();
        $this->delete(route('admin.roles.destroy', $role))->assertRedirect();

        $this->assertSame([
            'created:editor',
            'synced:editor',
            'updated:editor>senior-editor',
            'synced:senior-editor',
            'deleted:senior-editor',
        ], CustomRole::$events);
    }

    public function test_permission_writes_go_through_the_configured_model(): void
    {
        $this->post(route('admin.permissions.store'), ['name' => 'view-posts'])->assertRedirect();

        $permission = CustomPermission::findByName('view-posts');
        $this->put(route('admin.permissions.update', $permission), ['name' => 'edit-posts'])->assertRedirect();
        $this->delete(route('admin.permissions.destroy', $permission))->assertRedirect();

        $this->assertSame([
            'created:view-posts',
            'updated:view-posts>edit-posts',
            'deleted:edit-posts',
        ], CustomPermission::$events);
    }

    public function test_a_failed_permission_sync_does_not_leave_a_new_role_behind(): void
    {
        CustomRole::$failSync = true;

        $this->withoutExceptionHandling();

        try {
            $this->post(route('admin.roles.store'), ['name' => 'editor']);
            $this->fail('The sync should have thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The permission sync failed.', $e->getMessage());
        }

        $this->assertDatabaseMissing('roles', ['name' => 'editor']);
    }

    public function test_a_failed_permission_sync_does_not_keep_the_new_name(): void
    {
        $role = CustomRole::create(['name' => 'editor', 'guard_name' => 'web']);
        CustomRole::$failSync = true;

        $this->withoutExceptionHandling();

        try {
            $this->put(route('admin.roles.update', $role), ['name' => 'senior-editor']);
            $this->fail('The sync should have thrown.');
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'editor']);
    }
}
