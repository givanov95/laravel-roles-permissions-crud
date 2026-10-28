<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

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
     */
    public static function guard(): string
    {
        return (string) config('roles-permissions-crud.guard', 'web');
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
