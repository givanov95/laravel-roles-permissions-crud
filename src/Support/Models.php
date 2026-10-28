<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
     * The roles of the package's guard: every query that lists or looks up roles starts here.
     *
     * @return Builder<Role>
     */
    public static function roles(): Builder
    {
        return self::roleClass()::query()->where('guard_name', self::guard());
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
     * The route-bound role as the configured class. The route binds spatie's class, so that the routes
     * also work when cached; this swaps the instance without another query.
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
