<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;

/**
 * Keeps whoever may manage roles from handing out more than they hold, changing a role that is
 * beyond them, or stripping a protected role. Switched by `prevent_privilege_escalation`.
 *
 * The user is the application's, with spatie's HasRoles trait.
 */
final class EscalationGuard
{
    public static function enabled(): bool
    {
        return (bool) config('roles-permissions-crud.prevent_privilege_escalation', true);
    }

    /**
     * The ids of the permissions the user holds. "Holds" is what the user can do, not what is stored for
     * them, so a user who is let through by a `Gate::before` (the usual super admin) holds everything.
     *
     * @return Collection<int, int>
     */
    public static function held(mixed $user): Collection
    {
        if (! $user instanceof Authorizable) {
            return collect();
        }

        return Models::permissions()
            ->get(['id', 'name'])
            ->filter(fn (PermissionContract $permission) => $user->can($permission->name))
            ->pluck('id')
            ->values();
    }

    /**
     * A role is the user's to change only if they hold every permission it carries. Pass `$held` when
     * checking many roles, so it is worked out once.
     *
     * @param  Collection<int, int>|null  $held
     */
    public static function canManageRole(mixed $user, RoleContract $role, ?Collection $held = null): bool
    {
        return ! self::enabled() || $role->permissions->pluck('id')->diff($held ?? self::held($user))->isEmpty();
    }

    /**
     * A permission is the user's to rename or delete only if they hold it, or if nobody has it: otherwise
     * renaming or deleting it would change roles or users beyond them. (Every role that carries a
     * permission the user does not hold is beyond them, so "nobody has it" is all that is left.)
     */
    public static function canManagePermission(mixed $user, PermissionContract $permission): bool
    {
        return ! self::enabled()
            || ($user instanceof Authorizable && $user->can($permission->name))
            || ! self::permissionInUse($permission);
    }

    /**
     * A rename hands the new name to everyone who has the permission, so while it is in use the user may
     * rename it only to a permission they can use themselves.
     */
    public static function canRenamePermission(mixed $user, PermissionContract $permission, string $name): bool
    {
        return ! self::enabled()
            || $name === $permission->name
            || ! self::permissionInUse($permission)
            || ($user instanceof Authorizable && $user->can($name));
    }

    /**
     * The permissions that guard the screens (`authorize_permissions`) which the user would lose if the
     * role, one of their own, ended up with only `$remaining` (none when it is deleted) and they hold
     * them through nothing else. Whoever manages access cannot lock themselves out of it.
     *
     * @param  Collection<int, int>  $remaining
     * @return Collection<int, string>
     */
    public static function locksOut(mixed $user, RoleContract $role, Collection $remaining): Collection
    {
        if (! self::enabled() || ! $user instanceof Model || ! method_exists($user, 'roles') || ! method_exists($user, 'permissions')) {
            return collect();
        }

        /** @var Collection<int, RoleContract&Model> $roles */
        $roles = $user->roles()->where('guard_name', Models::guard())->with('permissions:id,name')->get();

        if (! $roles->contains(fn (Model $own) => $own->getKey() === $role->getKey())) {
            return collect();
        }

        $elsewhere = $user->permissions()->where('guard_name', Models::guard())->pluck('name')
            ->merge($roles->reject(fn (Model $own) => $own->getKey() === $role->getKey())->flatMap(
                fn (RoleContract $own) => $own->permissions->pluck('name')
            ));

        return $role->permissions
            ->reject(fn (PermissionContract $permission) => $remaining->contains($permission->id))
            ->pluck('name')
            ->intersect(Authorizer::guardingPermissions())
            ->diff($elsewhere)
            ->sort()
            ->values();
    }

    /**
     * Whether a role is assigned to anyone. Read from the pivot, as the roles list does, because spatie's
     * Role::users() resolves its model from the active auth guard.
     */
    public static function roleInUse(RoleContract $role): bool
    {
        [$table, $key] = Models::rolePivot();

        return DB::table($table)->where($key, $role->id)->exists();
    }

    /**
     * Whether a permission is given to a role or straight to anyone.
     */
    public static function permissionInUse(PermissionContract $permission): bool
    {
        return $permission->roles()->exists()
            || DB::table(config('permission.table_names.model_has_permissions', 'model_has_permissions'))
                ->where(config('permission.column_names.permission_pivot_key') ?? 'permission_id', $permission->id)
                ->exists();
    }

    /**
     * Whether a role or a permission that is in use may be deleted (`allow_delete_in_use`).
     */
    public static function deletesInUse(): bool
    {
        return (bool) config('roles-permissions-crud.allow_delete_in_use', false);
    }

    /**
     * The permissions in a request that the user would be adding to the role without holding them.
     *
     * @param  Collection<int, int>  $requested
     * @return Collection<int, int>
     */
    public static function beyondTheUser(mixed $user, ?RoleContract $role, Collection $requested): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        return $requested
            ->diff($role?->permissions->pluck('id') ?? collect())
            ->diff(self::held($user))
            ->values();
    }

    /**
     * Whether a protected role would lose a permission with this request.
     *
     * @param  Collection<int, int>  $requested
     */
    public static function stripsProtectedRole(?RoleContract $role, Collection $requested): bool
    {
        return self::enabled()
            && $role !== null
            && self::isProtectedRole($role)
            && $role->permissions->pluck('id')->diff($requested)->isNotEmpty();
    }

    public static function isProtectedRole(RoleContract $role): bool
    {
        // Fall back to spatie's own protected_roles config when the package key is left empty, so apps
        // that already define permission.protected_roles need no extra configuration.
        return in_array($role->name, self::protectedRoles(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function protectedRoles(): array
    {
        $protected = config('roles-permissions-crud.protected_roles') ?: config('permission.protected_roles', []);

        return array_values((array) $protected);
    }

    /**
     * Permissions that cannot be renamed or deleted: the ones that guard the screens, the ones listed
     * in `protected_permissions`, and, while escalation is prevented, the ones a protected role holds.
     *
     * @return array<int, string>
     */
    public static function protectedPermissionNames(): array
    {
        $names = [
            ...Authorizer::guardingPermissions(),
            ...array_values((array) config('roles-permissions-crud.protected_permissions', [])),
        ];

        if (self::enabled() && self::protectedRoles() !== []) {
            $names = [
                ...$names,
                ...Models::permissions()
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', self::protectedRoles()))
                    ->pluck('name')
                    ->all(),
            ];
        }

        return array_values(array_unique($names));
    }
}
