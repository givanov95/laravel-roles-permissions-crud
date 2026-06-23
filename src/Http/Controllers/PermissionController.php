<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Requests\StorePermissionRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render($this->page('permissions/Index'), [
            'permissions' => Permission::query()
                ->withCount('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (Permission $permission) => [
                    'id'          => $permission->id,
                    'name'        => $permission->name,
                    'roles_count' => $permission->roles_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render($this->page('permissions/Create'));
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        Permission::create([
            'name'       => $request->validated('name'),
            'guard_name' => config('roles-permissions-crud.guard', 'web'),
        ]);

        return redirect()
            ->route($this->routeName('permissions.index'))
            ->with('success', __('The permission has been created.'));
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render($this->page('permissions/Edit'), [
            'permission' => [
                'id'   => $permission->id,
                'name' => $permission->name,
            ],
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update(['name' => $request->validated('name')]);

        return redirect()
            ->route($this->routeName('permissions.index'))
            ->with('success', __('The permission has been updated.'));
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        $permission->delete();

        return redirect()
            ->route($this->routeName('permissions.index'))
            ->with('success', __('The permission has been deleted.'));
    }

    private function page(string $path): string
    {
        return trim(config('roles-permissions-crud.page_prefix', 'admin'), '/').'/'.$path;
    }

    private function routeName(string $name): string
    {
        return config('roles-permissions-crud.route_name_prefix', 'admin.').$name;
    }
}
