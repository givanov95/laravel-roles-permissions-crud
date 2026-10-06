<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Reads `authorize_permissions`, which names the permission each screen needs. A resource's value is
 * one of:
 *
 *  - a string: one permission for reading and writing (how it worked before they were split);
 *  - `['view' => ..., 'manage' => ...]`: one for listing, one for changing; a null part is not checked;
 *  - null: nothing is checked, the application guards the routes itself.
 */
final class Authorizer
{
    public const VIEW = 'view';

    public const MANAGE = 'manage';

    /**
     * @param  'roles'|'permissions'  $resource
     * @param  'view'|'manage'  $ability
     */
    public static function permission(string $resource, string $ability): ?string
    {
        $configured = config('roles-permissions-crud.authorize_permissions.'.$resource);

        if (is_array($configured)) {
            $configured = $configured[$ability] ?? null;
        }

        return is_string($configured) ? $configured : null;
    }

    /**
     * @param  'roles'|'permissions'  $resource
     * @param  'view'|'manage'  $ability
     */
    public static function allows(mixed $user, string $resource, string $ability): bool
    {
        $permission = self::permission($resource, $ability);

        return $permission === null || ($user instanceof Authorizable && $user->can($permission));
    }

    /**
     * Every permission the screens are guarded with.
     *
     * @return array<int, string>
     */
    public static function guardingPermissions(): array
    {
        $names = [];

        foreach ((array) config('roles-permissions-crud.authorize_permissions', []) as $configured) {
            foreach ((array) $configured as $name) {
                if (is_string($name)) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The `permission:` route middleware for a resource and ability, none when it is not checked.
     *
     * @param  'roles'|'permissions'  $resource
     * @param  'view'|'manage'  $ability
     * @return array<int, string>
     */
    public static function middleware(string $resource, string $ability): array
    {
        $permission = self::permission($resource, $ability);

        return $permission === null ? [] : ['permission:'.$permission];
    }
}
