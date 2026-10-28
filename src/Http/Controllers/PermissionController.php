<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\AuthorizesCrudAccess;
use Givanov95\RolesPermissionsCrud\Http\Requests\StorePermissionRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdatePermissionRequest;
use Givanov95\RolesPermissionsCrud\Lists\PermissionList;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

    /**
     * The permissions, paged, searched and sorted on the server. Bind your own {@see PermissionList} to
     * change the query, the columns or the rows.
     */
    public function index(Request $request, PermissionList $list): Response
    {
        return Inertia::render($this->page('permissions/Index'), [
            'dataTable' => $list->payload($request),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render($this->page('permissions/Create'));
    }

    public function store(StorePermissionRequest $request): RedirectResponse
    {
        DB::transaction(fn () => Models::permissionClass()::create([
            'name'       => $request->validated('name'),
            'guard_name' => Models::guard(),
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been created.')]);

        return redirect()->route($this->routeName('permissions.index'));
    }

    public function edit(Permission $permission): Response
    {
        abort_unless(EscalationGuard::canManagePermission(request()->user(), $permission), 403);

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
            DB::transaction(fn () => Models::permission($permission)->update(['name' => $request->validated('name')]));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been updated.')]);

        return redirect()->route($this->routeName('permissions.edit'), $permission);
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        abort_unless(EscalationGuard::canManagePermission(request()->user(), $permission), 403);

        if ($this->isProtected($permission)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This permission is protected and cannot be deleted.')]);

            return redirect()->route($this->routeName('permissions.index'));
        }

        if (! EscalationGuard::deletesInUse() && EscalationGuard::permissionInUse($permission)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This permission is in use and cannot be deleted. Take it away from every role and user first.')]);

            return redirect()->route($this->routeName('permissions.index'));
        }

        DB::transaction(fn () => Models::permission($permission)->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been deleted.')]);

        return redirect()->route($this->routeName('permissions.index'));
    }

    protected function isProtected(Permission $permission): bool
    {
        return in_array($permission->name, EscalationGuard::protectedPermissionNames(), true);
    }

    protected function page(string $path): string
    {
        $prefix = trim((string) config('roles-permissions-crud.page_prefix', 'admin'), '/');

        return $prefix === '' ? $path : $prefix.'/'.$path;
    }

    protected function routeName(string $name): string
    {
        return config('roles-permissions-crud.route_name_prefix', 'admin.').$name;
    }
}
