<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    /** A permission name that does not exist, for {@see canRenamePermissionAtAll()}. */
    private const NO_SUCH_PERMISSION = 'roles-permissions-crud: no such permission';

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

        $resolve = fn (): Collection => Models::permissions()
            ->get(['id', 'name'])
            ->filter(fn (PermissionContract $permission) => $user->can($permission->name))
            ->pluck('id')
            ->values();

        // Remembered only for a stored user: an unsaved one has no key to tell it apart from another.
        if (! $user instanceof Model || $user->getKey() === null) {
            return $resolve();
        }

        // The guard and the team are part of the key: what the user holds depends on both, and the
        // application may change either during the request.
        $key = implode(':', [Models::guard(), Models::teams() ? (string) Models::team() : '', $user->getMorphClass(), $user->getKey()]);

        return app(HeldPermissions::class)->remember(app('request'), $key, $resolve);
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
     *
     * @param  Collection<int, int>|null  $held
     */
    public static function canManagePermission(mixed $user, PermissionContract $permission, ?Collection $held = null, ?bool $inUse = null): bool
    {
        if (! self::enabled()) {
            return true;
        }

        // A list passes what it already knows ($held, $inUse from its counts), so a page of rows does
        // not cost two queries each.
        $holds = $held !== null
            ? $held->contains($permission->id)
            : $user instanceof Authorizable && $user->can($permission->name);

        return $holds || ! ($inUse ?? self::permissionInUse($permission));
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
     * False only when the user certainly cannot rename the permission ({@see canRenamePermission()}), so
     * the form locks the name instead of refusing it on save. A new name is a permission that does not
     * exist yet: without wildcards, only a user who may use every permission (a `Gate::before` super
     * admin) can use one, which is asked with a name no permission has. With spatie's wildcards a user
     * may hold names that do not exist (`posts.*` covers `posts.read`), so the form cannot tell in
     * advance and the save decides.
     */
    public static function canRenamePermissionAtAll(mixed $user, PermissionContract $permission, ?bool $inUse = null): bool
    {
        return ! self::enabled()
            || ! ($inUse ?? self::permissionInUse($permission))
            || (bool) config('permission.enable_wildcard_permission', false)
            || ($user instanceof Authorizable && $user->can(self::NO_SUCH_PERMISSION));
    }

    /**
     * The permissions that guard the screens (`authorize_permissions`) which the user would lose if the
     * role, one of their own, ended up with only `$remaining` (none when it is deleted) and they hold
     * them through nothing else. Whoever manages access cannot lock themselves out of it.
     *
     * Pass `$own` ({@see ownAccess()}) when checking many roles, so the user's roles and permissions
     * are read once. Without it they are read from the database for this check, so a change made
     * earlier in the request is seen.
     *
     * @param  Collection<int, int>  $remaining
     * @return Collection<int, string>
     */
    public static function locksOut(mixed $user, RoleContract $role, Collection $remaining, ?OwnAccess $own = null): Collection
    {
        if (! self::enabled()) {
            return collect();
        }

        $own ??= self::ownAccess($user);

        if (! $own->includes($role)) {
            return collect();
        }

        $elsewhere = $own->heldWithout($role);

        return $role->permissions
            ->reject(fn (PermissionContract $permission) => $remaining->contains($permission->id))
            ->pluck('name')
            ->intersect(Authorizer::guardingPermissions())
            ->diff($elsewhere)
            ->sort()
            ->values();
    }

    /**
     * The user's roles (with their permissions) and direct permissions in the package's guard, which
     * {@see locksOut()} weighs. None when escalation is not prevented or the user has no roles and
     * permissions of their own (no HasRoles).
     *
     * Read from the database on every call, never remembered or taken from spatie's cache or the
     * user's loaded relations: a check that keeps the user from locking themselves out must weigh
     * what is stored now. A list reads it once for its page and passes it to each row, as nothing
     * changes while the page is built; code that changes roles between checks should not. The roles
     * list reads it only for a page with one of the user's roles, which it tells by
     * {@see ownRoleIds()}, and passes `OwnAccess::none()` for the other rows.
     */
    public static function ownAccess(mixed $user): OwnAccess
    {
        $own = self::ownQueries($user);

        if ($own === null) {
            return OwnAccess::none();
        }

        [$roles, $permissions] = $own;

        return new OwnAccess($roles->with('permissions:id,name')->get(), $permissions->pluck('name'));
    }

    /**
     * The ids of the roles {@see ownAccess()} reads, in one query: a list asks it whether a page has any
     * of the user's roles before reading their access (three queries) for them. Read from the database,
     * as `ownAccess()` is.
     *
     * @return Collection<int, int|string>
     */
    public static function ownRoleIds(mixed $user): Collection
    {
        $own = self::ownQueries($user);

        if ($own === null) {
            return collect();
        }

        [$roles] = $own;

        return $roles->pluck($roles->getRelated()->getQualifiedKeyName());
    }

    /**
     * The user's roles and direct permissions in the package's guard (spatie's relations, so inside a
     * team the team's), or null when there is nothing to weigh. One place for {@see ownAccess()} and
     * {@see ownRoleIds()}, so the two cannot disagree about which roles are the user's: a miss would
     * skip the lock-out check.
     *
     * @return array{0: BelongsToMany<Model&RoleContract, Model>, 1: BelongsToMany<Model, Model>}|null
     */
    private static function ownQueries(mixed $user): ?array
    {
        if (! self::enabled() || ! $user instanceof Model || ! method_exists($user, 'roles') || ! method_exists($user, 'permissions')) {
            return null;
        }

        return [
            $user->roles()->where('guard_name', Models::guard()),
            $user->permissions()->where('guard_name', Models::guard()),
        ];
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
        [$table, $key] = Models::permissionPivot();

        return $permission->roles()->exists() || DB::table($table)->where($key, $permission->id)->exists();
    }

    /**
     * Why the user cannot delete a role, or null when they can. Beyond {@see canManageRole()}, which is a
     * 403 of its own. The delete and the roles list both ask this, so the list offers Delete only when
     * the delete goes through.
     *
     * For many roles, pass `$inUse` (from the list's counts) and `$ownRoleIds`: the user's
     * {@see ownAccess()}, read once for all of them, as only the user's own roles can lock them out and
     * those are then weighed without queries. It still takes the ids of the user's roles, as before
     * `OwnAccess` (the name stays for callers that pass it by name): then the others skip the check,
     * and the user's own ones read their access each. An application's own list that passed the ids
     * passes `ownAccess()` instead to read it once.
     *
     * @param  OwnAccess|Collection<int, int|string>|null  $ownRoleIds
     */
    public static function roleDeleteRefusal(mixed $user, RoleContract $role, ?bool $inUse = null, OwnAccess|Collection|null $ownRoleIds = null): ?string
    {
        if (self::isProtectedRole($role)) {
            return __('This role is protected and cannot be deleted.');
        }

        $own = $ownRoleIds instanceof Collection
            ? ($ownRoleIds->contains($role->id) ? null : OwnAccess::none())
            : $ownRoleIds;

        $lost = self::locksOut($user, $role, collect(), $own);

        if ($lost->isNotEmpty()) {
            return __('You cannot delete this role: you would lose :permissions, which you need to manage access.', [
                'permissions' => $lost->implode(', '),
            ]);
        }

        if (! self::deletesInUse() && ($inUse ?? self::roleInUse($role))) {
            return __('This role is assigned and cannot be deleted. Take it away from everyone first.');
        }

        return null;
    }

    /**
     * Why a permission cannot be deleted, or null when it can (see {@see roleDeleteRefusal()}). For many
     * permissions, pass the protected names and `$inUse` from the list.
     *
     * @param  array<int, string>|null  $protectedNames
     */
    public static function permissionDeleteRefusal(PermissionContract $permission, ?array $protectedNames = null, ?bool $inUse = null): ?string
    {
        if (in_array($permission->name, $protectedNames ?? self::protectedPermissionNames(), true)) {
            return __('This permission is protected and cannot be deleted.');
        }

        if (! self::deletesInUse() && ($inUse ?? self::permissionInUse($permission))) {
            return __('This permission is in use and cannot be deleted. Take it away from every role and user first.');
        }

        return null;
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
