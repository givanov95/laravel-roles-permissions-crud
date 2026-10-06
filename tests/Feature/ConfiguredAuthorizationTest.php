<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Feature;

use Givanov95\RolesPermissionsCrud\Tests\DefaultsTestCase;
use Spatie\Permission\Models\Role;

/**
 * `authorize_permissions` accepts a single permission per resource (one permission for reading and
 * writing, as before the split), a view/manage pair, and null for "check nothing".
 */
class ConfiguredAuthorizationTest extends DefaultsTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('roles-permissions-crud.authorize_permissions', [
            // The old shape: one permission for everything on the screen.
            'roles' => 'manage-everything-about-roles',
            // Only writing is checked; anyone who gets this far may look.
            'permissions' => ['view' => null, 'manage' => 'edit-permissions'],
        ]);
    }

    public function test_a_single_permission_guards_both_reading_and_writing(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $holder = $this->userWith(['manage-everything-about-roles']);
        $other = $this->userWith(['view-roles', 'manage-roles']);

        $this->actingAs($holder)->get(route('admin.roles.index'), $this->inertia())->assertOk();
        $this->actingAs($holder)->put(route('admin.roles.update', $role), ['name' => 'writer'])->assertRedirect();
        $this->assertSame('writer', $role->fresh()->name);

        $this->actingAs($other)->get(route('admin.roles.index'), $this->inertia())->assertForbidden();
        $this->actingAs($other)->put(route('admin.roles.update', $role), ['name' => 'again'])->assertForbidden();
    }

    public function test_a_null_part_is_not_checked(): void
    {
        $anyone = $this->userWith();
        $editor = $this->userWith(['edit-permissions']);

        $this->actingAs($anyone)->get(route('admin.permissions.index'), $this->inertia())->assertOk();
        $this->actingAs($anyone)->post(route('admin.permissions.store'), ['name' => 'reports.export'])->assertForbidden();

        $this->actingAs($editor)->post(route('admin.permissions.store'), ['name' => 'reports.export'])->assertRedirect(route('admin.permissions.index'));
    }
}
