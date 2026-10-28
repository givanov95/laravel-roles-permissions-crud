<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Lists;

use Givanov95\DataTable\DataTable;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
    /** @var Collection<int, int> */
    private Collection $held;

    private bool $mayManage = false;

    protected function prepare(Request $request): void
    {
        $this->held = EscalationGuard::held($request->user());
        $this->mayManage = Authorizer::allows($request->user(), 'roles', Authorizer::MANAGE);
    }

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
        $model = $roles->getModel();

        $key = $model->getQualifiedKeyName();

        return $roles
            ->select("{$model->getTable()}.*")
            ->selectSub($this->usersCount($key), 'users_count')
            ->selectSub($this->othersCount($key), 'others_count')
            // Every assignment, as EscalationGuard::roleInUse() counts them for a delete.
            ->selectSub($this->assignments($key), 'assignments_count')
            ->with('permissions:id,name');
    }

    /**
     * The users who have the role, counted on the pivot: spatie's Role::users() resolves its model from
     * the active auth guard, which may be a token guard during the request. Only the guard's user model
     * is counted, not other models that were given the role.
     */
    protected function usersCount(string $rolesKey): QueryBuilder
    {
        [$pivot] = Models::rolePivot();
        $query = $this->inTeam($this->assignments($rolesKey));
        $userType = $this->userMorphClass();

        if ($userType !== null) {
            $query->where("{$pivot}.model_type", $userType);
        }

        return $query;
    }

    /**
     * The other models that have the role (anything but the guard's users), counted like the users: they
     * keep a role in use, so the list says why it cannot be deleted.
     */
    protected function othersCount(string $rolesKey): QueryBuilder
    {
        [$pivot] = Models::rolePivot();
        $userType = $this->userMorphClass();

        return $userType === null
            ? DB::query()->selectRaw('0')
            : $this->inTeam($this->assignments($rolesKey))->where("{$pivot}.model_type", '!=', $userType);
    }

    private function assignments(string $rolesKey): QueryBuilder
    {
        [$pivot, $roleKey] = Models::rolePivot();

        return DB::table($pivot)->selectRaw('count(*)')->whereColumn("{$pivot}.{$roleKey}", $rolesKey);
    }

    /**
     * Inside a team, the assignments in that team.
     */
    private function inTeam(QueryBuilder $query): QueryBuilder
    {
        [$pivot] = Models::rolePivot();

        return Models::inTeam() ? $query->where("{$pivot}.".Models::teamKey(), Models::team()) : $query;
    }

    private function userMorphClass(): ?string
    {
        $userModel = Guard::getModelForGuard(Models::guard());

        return is_string($userModel) && is_a($userModel, Model::class, true) ? (new $userModel)->getMorphClass() : null;
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Role $model */
        $user = $request->user();
        $isProtected = EscalationGuard::isProtectedRole($model);
        // Whether the user may open, edit and delete it: the manage permission, and the role not beyond them.
        $canManage = $this->mayManage
            && ! Models::sharedAcrossTeams($model)
            && EscalationGuard::canManageRole($user, $model, $this->held);

        return [
            'id'           => $model->getKey(),
            'name'         => $model->name,
            'users_count'  => (int) $model->getAttribute('users_count'),
            'others_count' => (int) $model->getAttribute('others_count'),
            'permissions'  => $model->permissions->pluck('name')->values()->all(),
            'is_protected' => $isProtected,
            'can_manage'   => $canManage,
            // What RoleController::destroy() lets through, so the list offers no Delete that ends in an error.
            'can_delete'   => $canManage
                && ! $isProtected
                && (EscalationGuard::deletesInUse() || (int) $model->getAttribute('assignments_count') === 0),
        ];
    }
}
