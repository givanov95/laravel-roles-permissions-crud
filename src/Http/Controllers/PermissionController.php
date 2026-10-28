<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\AuthorizesCrudAccess;
use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\RedirectsToIndex;
use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\RendersPages;
use Givanov95\RolesPermissionsCrud\Http\Requests\StorePermissionRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdatePermissionRequest;
use Givanov95\RolesPermissionsCrud\Lists\PermissionList;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    use AuthorizesCrudAccess;
    use RedirectsToIndex;
    use RendersPages;

    public function __construct()
    {
        $this->middleware(self::crudAccess('permissions'));
    }

    /**
     * The permissions, paged, searched and sorted on the server. Bind your own {@see PermissionList} to
     * change the query, the columns or the rows.
     */
    public function index(): Response
    {
        return $this->render('permissions/Index', [
            'dataTable' => app(PermissionList::class)->payload(request()),
        ], [
            // Whether the user may add records; each row says whether it may be edited (`can_manage`).
            // Permissions are shared by every team, so they are not added from inside one.
            'canCreate' => Authorizer::allows(request()->user(), 'permissions', Authorizer::MANAGE) && ! Models::inTeam(),
        ]);
    }

    public function create(): Response
    {
        return $this->render('permissions/Create');
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
        $inUse = EscalationGuard::permissionInUse($permission);

        abort_unless(EscalationGuard::canManagePermission(request()->user(), $permission, null, $inUse), 403);

        return $this->render('permissions/Edit', [
            'permission' => [
                'id'           => $permission->id,
                'name'         => $permission->name,
                'is_protected' => $this->isProtected($permission),
                // A rename hands the new name to everyone who has the permission, so one in use is renamed
                // only by a user who may use every permission; the form locks the name for the rest.
                'can_rename'   => EscalationGuard::canRenamePermissionAtAll(request()->user(), $permission, $inUse),
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

        $refusal = EscalationGuard::permissionDeleteRefusal($permission);

        if ($refusal !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $refusal]);

            return $this->redirectToIndex('permissions');
        }

        DB::transaction(fn () => Models::permission($permission)->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The permission has been deleted.')]);

        return $this->redirectToIndex('permissions');
    }

    protected function isProtected(Permission $permission): bool
    {
        return in_array($permission->name, EscalationGuard::protectedPermissionNames(), true);
    }
}
