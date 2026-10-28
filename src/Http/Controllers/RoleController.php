<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers;

use Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns\AuthorizesCrudAccess;
use Givanov95\RolesPermissionsCrud\Http\Requests\StoreRoleRequest;
use Givanov95\RolesPermissionsCrud\Http\Requests\UpdateRoleRequest;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use AuthorizesCrudAccess;

    public function __construct()
    {
        $this->middleware(self::crudAccess('roles'));
    }

    public function index(): Response
    {
        // Count assignments via the pivot directly. Spatie's Role::users()
        // relation resolves the related model from the *active* auth guard,
        // which during a request may be a token guard (no matching model) and
        // would blow up withCount(). Reading the pivot is guard-independent.
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

        $userCounts = DB::table($pivotTable)
            ->select($roleKey)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($roleKey)
            ->pluck('aggregate', $roleKey);

        $user = request()->user();
        $held = EscalationGuard::held($user);

        return Inertia::render($this->page('roles/Index'), [
            'roles' => Models::roles()
                ->with('permissions:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (RoleContract $role) => [
                    'id'           => $role->id,
                    'name'         => $role->name,
                    'users_count'  => (int) ($userCounts[$role->id] ?? 0),
                    'permissions'  => $role->permissions->pluck('name'),
                    'is_protected' => EscalationGuard::isProtectedRole($role),
                    'can_manage'   => EscalationGuard::canManageRole($user, $role, $held),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render($this->page('roles/Create'), [
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

        return Inertia::render($this->page('roles/Edit'), [
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

            return redirect()->route($this->routeName('roles.index'));
        }

        $lost = EscalationGuard::locksOut(request()->user(), $role, collect());

        if ($lost->isNotEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You cannot delete this role: you would lose :permissions, which you need to manage access.', [
                'permissions' => $lost->implode(', '),
            ])]);

            return redirect()->route($this->routeName('roles.index'));
        }

        if (! EscalationGuard::deletesInUse() && EscalationGuard::roleInUse($role)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This role is assigned and cannot be deleted. Take it away from everyone first.')]);

            return redirect()->route($this->routeName('roles.index'));
        }

        DB::transaction(fn () => Models::role($role)->delete());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The role has been deleted.')]);

        return redirect()->route($this->routeName('roles.index'));
    }

    /**
     * The permissions the form offers: only what the user holds while escalation is prevented, plus the
     * ones the role already has, so that saving the form does not drop them.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function permissionOptions(?Role $role = null): array
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
