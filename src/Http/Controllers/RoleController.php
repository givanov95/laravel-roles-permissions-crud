<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\AuthorizesCrudAccess;
use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\RedirectsToIndex;
use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\RendersPages;
use Givanov95\RolesPermissionsCrud\Http\Requests\StoreRoleRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdateRoleRequest;
use Givanov95\RolesPermissionsCrud\Lists\RoleList;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesCrudAccess;
    use RedirectsToIndex;
    use RendersPages;

    public function __construct()
    {
        $this->middleware(self::crudAccess('roles'));
    }

    /**
     * The roles, paged, searched and sorted on the server. Bind your own {@see RoleList} to change the
     * query, the columns or the rows.
     */
    public function index(): Response
    {
        return $this->render('roles/Index', [
            'dataTable' => app(RoleList::class)->payload(request()),
        ], [
            // Whether the user may add records; each row says whether it may be edited (`can_manage`).
            'canCreate' => Authorizer::allows(request()->user(), 'roles', Authorizer::MANAGE),
        ]);
    }

    public function create(): Response
    {
        return $this->render('roles/Create', [
            'permissions' => $this->permissionOptions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        // One unit: a failed permission sync must not leave a role behind.
        DB::transaction(function () use ($request): void {
            $role = Models::roleClass()::create([
                'name'       => $request->validated('name'),
                'guard_name' => Models::guard(),
            ]);

            // Cast to int: form values arrive as numeric strings and Spatie treats
            // a string permission as a *name* (not an id), so "2" would be looked up
            // by name and throw PermissionDoesNotExist. Ids must be integers.
            $role->syncPermissions(array_map('intval', $request->validated('permissions', [])));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The role has been created.')]);

        return redirect()->route($this->routeName('roles.index'));
    }

    public function edit(Role $role): Response
    {
        abort_unless(EscalationGuard::canManageRole(request()->user(), $role), 403);

        return $this->render('roles/Edit', [
            'role' => [
                'id'           => $role->id,
                'name'         => $role->name,
                'permissions'  => $role->permissions->pluck('id'),
                'is_protected' => EscalationGuard::isProtectedRole($role),
            ],
            'permissions' => $this->permissionOptions($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role = Models::role($role);

        DB::transaction(function () use ($request, $role): void {
            // Protected roles keep their name (it gates access) but their
            // permission set may still be adjusted.
            if (! EscalationGuard::isProtectedRole($role)) {
                $role->update(['name' => $request->validated('name')]);
            }

            // Cast to int: form values arrive as numeric strings and Spatie treats
            // a string permission as a *name* (not an id), so "2" would be looked up
            // by name and throw PermissionDoesNotExist. Ids must be integers.
            $role->syncPermissions(array_map('intval', $request->validated('permissions', [])));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The role has been updated.')]);

        return redirect()->route($this->routeName('roles.edit'), $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(EscalationGuard::canManageRole(request()->user(), $role), 403);

        if (EscalationGuard::isProtectedRole($role)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This role is protected and cannot be deleted.')]);

            return $this->redirectToIndex('roles');
        }

        $lost = EscalationGuard::locksOut(request()->user(), $role, collect());

        if ($lost->isNotEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete this role: you would lose :permissions, which you need to manage access.', [
                'permissions' => $lost->implode(', '),
            ])]);

            return $this->redirectToIndex('roles');
        }

        if (! EscalationGuard::deletesInUse() && EscalationGuard::roleInUse($role)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This role is assigned and cannot be deleted. Take it away from everyone first.')]);

            return $this->redirectToIndex('roles');
        }

        DB::transaction(fn () => Models::role($role)->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The role has been deleted.')]);

        return $this->redirectToIndex('roles');
    }

    /**
     * The permissions the form offers: only what the user holds while escalation is prevented, plus the
     * ones the role already has, so that saving the form does not drop them.
     *
     * @return array<int, array{value: int, label: string}>
     */
    protected function permissionOptions(?Role $role = null): array
    {
        $held = EscalationGuard::held(request()->user());
        $own = $role?->permissions->pluck('id') ?? collect();

        return Models::permissions()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->filter(fn (PermissionContract $permission) => ! EscalationGuard::enabled() || $held->contains($permission->id) || $own->contains($permission->id))
            ->map(fn (PermissionContract $permission) => [
                'value' => $permission->id,
                'label' => $permission->name,
            ])
            ->values()
            ->all();
    }
}
