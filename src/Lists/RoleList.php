<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Lists;

use Givanov95\DataTable\DataTable;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Guard;

/**
 * The roles of the package's guard: searched and sorted by name, sorted by the number of users.
 */
class RoleList extends ServerList
{
    /** @var Collection<int, int>|null */
    private ?Collection $held = null;

    protected function table(Request $request): DataTable
    {
        return (new DataTable($this->query(), $request))
            ->setColumn('name', __('Name'), searchable: true, orderable: true)
            ->setColumn('users_count', __('Users'), orderable: true);
    }

    /**
     * @return Builder<\Spatie\Permission\Models\Role>
     */
    protected function query(): Builder
    {
        $roles = Models::roles();
        $table = $roles->getModel()->getTable();

        return $roles
            ->select("{$table}.*")
            ->selectSub($this->usersCount($table), 'users_count')
            ->with('permissions:id,name');
    }

    /**
     * The users who have the role, counted on the pivot: spatie's Role::users() resolves its model from
     * the active auth guard, which may be a token guard during the request. Only the guard's user model
     * is counted, not other models that were given the role.
     */
    protected function usersCount(string $rolesTable): \Illuminate\Database\Query\Builder
    {
        [$pivot, $roleKey] = Models::rolePivot();
        $query = DB::table($pivot)->selectRaw('count(*)')->whereColumn("{$pivot}.{$roleKey}", "{$rolesTable}.id");
        $userModel = Guard::getModelForGuard(Models::guard());

        if (is_string($userModel) && is_a($userModel, Model::class, true)) {
            $query->where("{$pivot}.model_type", (new $userModel)->getMorphClass());
        }

        return $query;
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Role $model */
        $user = $request->user();
        $this->held ??= EscalationGuard::held($user);

        return [
            'id'           => $model->getKey(),
            'name'         => $model->name,
            'users_count'  => (int) $model->getAttribute('users_count'),
            'permissions'  => $model->permissions->pluck('name')->values()->all(),
            'is_protected' => EscalationGuard::isProtectedRole($model),
            'can_manage'   => EscalationGuard::canManageRole($user, $model, $this->held),
        ];
    }
}
