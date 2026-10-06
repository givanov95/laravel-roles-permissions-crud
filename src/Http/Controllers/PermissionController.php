<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\AuthorizesCrudAccess;
use Givanov95\RolesPermissionsCrud\Http\Requests\StorePermissionRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use AuthorizesCrudAccess;

    public function __construct()
    {
        $this->middleware(self::crudAccess('permissions'));
    }

    public function index(): Response
    {
        return Inertia::render($this->page('permissions/Index'), [
            'permissions' => Permission::query()
                ->withCount('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (Permission $permission) => [
                    'id'           => $permission->id,
                    'name'         => $permission->name,
                    'roles_count'  => $permission->roles_count,
                    'is_protected' => $this->isProtected($permission),
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been created.')]);

        return redirect()->route($this->routeName('permissions.index'));
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render($this->page('permissions/Edit'), [
            'permission' => [
                'id'           => $permission->id,
                'name'         => $permission->name,
                'is_protected' => $this->isProtected($permission),
            ],
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): RedirectResponse
    {
        // A protected permission keeps its name: it gates access, so a rename would lock people out.
        if (! $this->isProtected($permission)) {
            $permission->update(['name' => $request->validated('name')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been updated.')]);

        return redirect()->route($this->routeName('permissions.edit'), $permission);
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        if ($this->isProtected($permission)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This permission is protected and cannot be deleted.')]);

            return redirect()->route($this->routeName('permissions.index'));
        }

        $permission->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been deleted.')]);

        return redirect()->route($this->routeName('permissions.index'));
    }

    /**
     * The permissions that gate the screens themselves (`authorize_permissions`) are always
     * protected, deleting or renaming one would lock out whoever manages access; add more with
     * `protected_permissions`.
     */
    private function isProtected(Permission $permission): bool
    {
        $protected = [
            ...array_filter((array) config('roles-permissions-crud.authorize_permissions', [])),
            ...(array) config('roles-permissions-crud.protected_permissions', []),
        ];

        return in_array($permission->name, $protected, true);
    }

    private function page(string $path): string
    {
        $prefix = trim((string) config('roles-permissions-crud.page_prefix', 'admin'), '/');

        return $prefix === '' ? $path : $prefix.'/'.$path;
    }

    private function routeName(string $name): string
    {
        return config('roles-permissions-crud.route_name_prefix', 'admin.').$name;
    }
}
