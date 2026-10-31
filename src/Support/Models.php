<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The role and permission models the application configured in `permission.models.*`. An application
 * may extend spatie's models (observers, overridden methods), so every write goes through its classes.
 */
final class Models
{
    /**
     * @return class-string<Role>
     */
    public static function roleClass(): string
    {
        /** @var class-string<Role> */
        return app(PermissionRegistrar::class)->getRoleClass();
    }

    /**
     * @return class-string<Permission>
     */
    public static function permissionClass(): string
    {
        /** @var class-string<Permission> */
        return app(PermissionRegistrar::class)->getPermissionClass();
    }

    /**
     * The guard whose roles and permissions this package lists and changes (`roles-permissions-crud.guard`).
     * Left empty, it is the application's default guard, as spatie does for a model without a guard name.
     */
    public static function guard(): string
    {
        $guard = config('roles-permissions-crud.guard');

        return is_string($guard) && $guard !== '' ? $guard : (string) config('auth.defaults.guard', 'web');
    }

    /**
     * The roles of the package's guard: every query that lists or looks up roles starts here. With spatie
     * teams, the roles of the current team and the global ones (no team), as spatie looks a role up.
     *
     * @return Builder<Role>
     */
    public static function roles(): Builder
    {
        $query = self::roleClass()::query()->where('guard_name', self::guard());

        if (self::teams()) {
            $key = $query->getModel()->qualifyColumn(self::teamKey());
            $team = self::team();

            $query->where(fn (Builder $query) => $team === null
                ? $query->whereNull($key)
                : $query->whereNull($key)->orWhere($key, $team));
        }

        return $query;
    }

    /**
     * The rule that a role name is free within the guard. With teams, inside a team: among the team's
     * roles and the global ones, which spatie's Role::create() checks. Outside any team, where a global
     * role is made: among every role, since a global role is seen in every team and must not share a
     * name with a team's own.
     */
    public static function uniqueRoleName(): Unique
    {
        $rule = Rule::unique(self::roleClass(), 'name')->where('guard_name', self::guard());

        if (self::inTeam()) {
            $key = self::teamKey();
            $team = self::team();

            $rule->where(fn (QueryBuilder $query) => $query->whereNull($key)->orWhere($key, $team));
        }

        return $rule;
    }

    /**
     * With teams, the names of `protected_roles` are reserved: a team cannot make a role of that name
     * through the screens (or rename one to it), which would make it protected and protect its
     * permissions for every team. The application creates those roles itself.
     *
     * @return array<int, mixed>
     */
    public static function reservedRoleNameRule(): array
    {
        return self::teams() ? [Rule::notIn(EscalationGuard::protectedRoles())] : [];
    }

    /**
     * Whether a role is one the screens show here: without teams, any; with teams, a global one or one
     * of the current team (as {@see roles()} lists them). A permission has no team.
     */
    public static function visibleInTeam(Model $record): bool
    {
        if (! self::teams() || ! $record instanceof RoleContract) {
            return true;
        }

        $team = $record->getAttribute(self::teamKey());

        return $team === null || (self::team() !== null && (string) $team === (string) self::team());
    }

    /**
     * Whether spatie's teams are on (`permission.teams`).
     */
    public static function teams(): bool
    {
        return (bool) app(PermissionRegistrar::class)->teams;
    }

    public static function teamKey(): string
    {
        return (string) app(PermissionRegistrar::class)->teamsKey;
    }

    /**
     * The current team (`getPermissionsTeamId()`), none without teams.
     */
    public static function team(): int|string|null
    {
        return self::teams() ? app(PermissionRegistrar::class)->getPermissionsTeamId() : null;
    }

    /**
     * Whether the screens are used inside a team: teams on and a current team set.
     */
    public static function inTeam(): bool
    {
        return self::team() !== null;
    }

    /**
     * Whether a role or permission is shared with other teams while the screens are used inside a team:
     * every permission (spatie's permissions have no team) and every global role. Changing one from a
     * team would change the access of the other teams, so inside a team they are read-only; they are
     * changed outside any team (no current team), such as in the application's own admin area.
     */
    public static function sharedAcrossTeams(Model $record): bool
    {
        if (! self::inTeam()) {
            return false;
        }

        return $record instanceof PermissionContract
            || ($record instanceof RoleContract && $record->getAttribute(self::teamKey()) === null);
    }

    /**
     * The permissions of the package's guard (see {@see roles()}).
     *
     * @return Builder<Permission>
     */
    public static function permissions(): Builder
    {
        return self::permissionClass()::query()->where('guard_name', self::guard());
    }

    /**
     * The primary key column of the configured permission model: `id` for spatie's, `uuid` (or anything
     * else) for an application's own model with another key.
     */
    public static function permissionKeyName(): string
    {
        return (new (self::permissionClass()))->getKeyName();
    }

    /**
     * Whether the configured permission model has an integer key; otherwise (a UUID or ULID) it is a string.
     */
    public static function permissionKeyIsInt(): bool
    {
        return in_array((new (self::permissionClass()))->getKeyType(), ['int', 'integer'], true);
    }

    /**
     * The rule a submitted permission key passes: an integer, or a string for a model with a UUID or ULID key.
     */
    public static function permissionKeyRule(): string
    {
        return self::permissionKeyIsInt() ? 'integer' : 'string';
    }

    /**
     * Submitted permission keys as the model stores them: form values arrive as strings, so an integer key
     * is cast back to an int, and any other key is kept as the string it is.
     *
     * @param  array<mixed>  $values
     * @return Collection<int, int|string>
     */
    public static function permissionKeys(array $values): Collection
    {
        $int = self::permissionKeyIsInt();

        return collect(array_values($values))->map(fn (mixed $key) => $int ? (int) $key : (string) $key);
    }

    /**
     * The primary keys of the records, whatever the key column is called.
     *
     * @param  iterable<Model>  $records
     * @return Collection<int, int|string>
     */
    public static function keys(iterable $records): Collection
    {
        return collect($records)->map(fn (Model $record) => $record->getKey())->values();
    }

    /**
     * The table that assigns roles to users (and other models) and its role key column.
     *
     * @return array{0: string, 1: string}
     */
    public static function rolePivot(): array
    {
        return [
            (string) config('permission.table_names.model_has_roles', 'model_has_roles'),
            (string) (config('permission.column_names.role_pivot_key') ?? 'role_id'),
        ];
    }

    /**
     * The table that gives permissions straight to users (and other models) and its permission key column.
     *
     * @return array{0: string, 1: string}
     */
    public static function permissionPivot(): array
    {
        return [
            (string) config('permission.table_names.model_has_permissions', 'model_has_permissions'),
            (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id'),
        ];
    }

    /**
     * The route-bound role as the configured class. The routes type-hint spatie's class, so that they also
     * work when cached, and the service provider has the container make the configured class for it, so
     * route binding finds the record by the configured model's key. Should an application bind spatie's
     * class to something else, this still swaps the instance without another query.
     */
    public static function role(Role $role): Role
    {
        return self::as(self::roleClass(), $role);
    }

    /**
     * The route-bound permission as the configured class (see {@see role()}).
     */
    public static function permission(Permission $permission): Permission
    {
        return self::as(self::permissionClass(), $permission);
    }

    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private static function as(string $class, Model $model): Model
    {
        if ($model instanceof $class) {
            return $model;
        }

        return (new $class)->newFromBuilder($model->getAttributes(), $model->getConnectionName());
    }
}
