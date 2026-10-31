<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Lists;

use Givanov95\DataTable\DataTable;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Contracts\Permission;

/**
 * The permissions of the package's guard: searched and sorted by name, sorted by the number of roles.
 */
class PermissionList extends ServerList
{
    /** @var array<int, string> */
    private array $protected = [];

    private bool $mayManage = false;

    /** @var Collection<int, int|string> */
    private Collection $held;

    protected function prepare(Request $request): void
    {
        $this->protected = EscalationGuard::protectedPermissionNames();
        $this->mayManage = Authorizer::allows($request->user(), 'permissions', Authorizer::MANAGE);
        $this->held = EscalationGuard::held($request->user());
    }

    protected function table(Request $request): DataTable
    {
        return (new DataTable($this->query(), $request))
            ->setColumn('name', __('Name'), searchable: true, orderable: true)
            ->setColumn('roles_count', __('Roles'), orderable: true);
    }

    /**
     * @return Builder<\Spatie\Permission\Models\Permission>
     */
    protected function query(): Builder
    {
        $permissions = Models::permissions();
        $key = $permissions->getModel()->getQualifiedKeyName();
        [$pivot, $permissionKey] = Models::permissionPivot();

        $direct = DB::table($pivot)->selectRaw('count(*)')->whereColumn("{$pivot}.{$permissionKey}", $key);

        // Inside a team, what that team sees: the roles of the team and the global ones, and what is given
        // straight to someone in the team. Other teams' set-up is not theirs to read.
        if (Models::inTeam()) {
            $direct->where("{$pivot}.".Models::teamKey(), Models::team());
        }

        return $permissions
            ->withCount(['roles' => fn (Builder $roles) => $roles->when(
                Models::inTeam(),
                fn (Builder $roles) => $roles->where(fn (Builder $roles) => $roles
                    ->whereNull($roles->qualifyColumn(Models::teamKey()))
                    ->orWhere($roles->qualifyColumn(Models::teamKey()), Models::team())),
            )])
            // Given straight to users: with the roles, whether the permission is in use.
            ->addSelect(['direct_count' => $direct]);
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Permission $model */
        $isProtected = in_array($model->name, $this->protected, true);
        // As EscalationGuard::permissionInUse() sees it: given to a role or straight to anyone. (Inside a
        // team the counts are the team's, but there permissions are read-only anyway.)
        $inUse = (int) $model->getAttribute('roles_count') > 0 || (int) $model->getAttribute('direct_count') > 0;
        // Whether the user may open, edit and delete it: the manage permission, and the permission not
        // reaching roles or users beyond them.
        $canManage = $this->mayManage
            && ! Models::inTeam()
            && EscalationGuard::canManagePermission($request->user(), $model, $this->held, $inUse);
        // Why PermissionController::destroy() would refuse it, so the list offers no Delete that ends in an
        // error and can say why. Not asked when the user cannot manage it: that is a 403.
        $refusal = $canManage ? EscalationGuard::permissionDeleteRefusal($model, $this->protected, $inUse) : null;

        return [
            'id'             => $model->getKey(),
            'name'           => $model->name,
            'roles_count'    => (int) $model->getAttribute('roles_count'),
            'direct_count'   => (int) $model->getAttribute('direct_count'),
            'is_protected'   => $isProtected,
            'can_manage'     => $canManage,
            'can_delete'     => $canManage && $refusal === null,
            'delete_refusal' => $refusal,
        ];
    }
}
