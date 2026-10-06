<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        return Permission::query()
            ->where('guard_name', config('roles-permissions-crud.guard', 'web'))
            ->get(['id', 'name'])
            ->filter(fn (Permission $permission) => $user->can($permission->name))
            ->pluck('id')
            ->values();
    }

    /**
     * A role is the user's to change only if they hold every permission it carries. Pass `$held` when
     * checking many roles, so it is worked out once.
     *
     * @param  Collection<int, int>|null  $held
     */
    public static function canManageRole(mixed $user, Role $role, ?Collection $held = null): bool
    {
        return ! self::enabled() || $role->permissions->pluck('id')->diff($held ?? self::held($user))->isEmpty();
    }

    /**
     * The permissions in a request that the user would be adding to the role without holding them.
     *
     * @param  Collection<int, int>  $requested
     * @return Collection<int, int>
     */
    public static function beyondTheUser(mixed $user, ?Role $role, Collection $requested): Collection
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
    public static function stripsProtectedRole(?Role $role, Collection $requested): bool
    {
        return self::enabled()
            && $role !== null
            && self::isProtectedRole($role)
            && $role->permissions->pluck('id')->diff($requested)->isNotEmpty();
    }

    public static function isProtectedRole(Role $role): bool
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
                ...Permission::query()
                    ->whereHas('roles', fn ($query) => $query->whereIn('name', self::protectedRoles()))
                    ->pluck('name')
                    ->all(),
            ];
        }

        return array_values(array_unique($names));
    }
}
