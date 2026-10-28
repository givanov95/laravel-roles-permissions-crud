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

    /** @var Collection<int, int> */
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

        return $permissions
            ->withCount('roles')
            // Given straight to users: with the roles, whether the permission is in use.
            ->addSelect(['direct_count' => DB::table($pivot)->selectRaw('count(*)')->whereColumn("{$pivot}.{$permissionKey}", $key)]);
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Permission $model */
        return [
            'id'           => $model->getKey(),
            'name'         => $model->name,
            'roles_count'  => (int) $model->getAttribute('roles_count'),
            'is_protected' => in_array($model->name, $this->protected, true),
            // Whether the user may open, edit and delete it: the manage permission, and the permission
            // not reaching roles or users beyond them.
            'can_manage'   => $this->mayManage && EscalationGuard::canManagePermission(
                $request->user(),
                $model,
                $this->held,
                (int) $model->getAttribute('roles_count') > 0 || (int) $model->getAttribute('direct_count') > 0,
            ),
        ];
    }
}
