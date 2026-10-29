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

    /**
     * The roles the user has, the only ones whose delete could lock them out. Read whenever escalation
     * is prevented, as the delete checks a lock-out before whether the role is in use: the row's
     * `delete_refusal` has to be the reason the delete gives.
     *
     * @var Collection<int, int|string>
     */
    private Collection $ownRoleIds;

    /** The guard's user model as stored in the pivot; false until worked out. */
    private string|false|null $userType = false;

    protected function prepare(Request $request): void
    {
        $user = $request->user();

        $this->held = EscalationGuard::held($user);
        $this->mayManage = Authorizer::allows($user, 'roles', Authorizer::MANAGE);
        $this->ownRoleIds = EscalationGuard::enabled() && $user instanceof Model && method_exists($user, 'roles')
            ? $user->roles()->pluck($user->roles()->getRelated()->getQualifiedKeyName())
            : collect();
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
        if ($this->userType === false) {
            $userModel = Guard::getModelForGuard(Models::guard());
            $this->userType = is_string($userModel) && is_a($userModel, Model::class, true) ? (new $userModel)->getMorphClass() : null;
        }

        return $this->userType;
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Role $model */
        $user = $request->user();
        // Whether the user may open, edit and delete it: the manage permission, and the role not beyond them.
        $canManage = $this->mayManage
            && ! Models::sharedAcrossTeams($model)
            && EscalationGuard::canManageRole($user, $model, $this->held);
        $users = (int) $model->getAttribute('users_count');
        $others = (int) $model->getAttribute('others_count');
        // Why RoleController::destroy() would refuse it, so the list offers no Delete that ends in an error
        // and can say why. Users and others together are every assignment, as EscalationGuard::roleInUse()
        // counts them. (Inside a team they are the team's; a role with assignments in other teams is a
        // global one, read-only there anyway.) Not asked when the user cannot manage it: that is a 403.
        $refusal = $canManage ? EscalationGuard::roleDeleteRefusal(
            $user,
            $model,
            $users + $others > 0,
            $this->ownRoleIds,
        ) : null;

        return [
            'id'             => $model->getKey(),
            'name'           => $model->name,
            'users_count'    => $users,
            'others_count'   => $others,
            'permissions'    => $model->permissions->pluck('name')->values()->all(),
            'is_protected'   => EscalationGuard::isProtectedRole($model),
            'can_manage'     => $canManage,
            'can_delete'     => $canManage && $refusal === null,
            'delete_refusal' => $refusal,
        ];
    }
}
