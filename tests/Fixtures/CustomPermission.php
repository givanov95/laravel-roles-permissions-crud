<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Fixtures;

use Spatie\Permission\Models\Permission;

/**
 * An application's own permission model, as configured in `permission.models.permission`. It notes
 * its model events.
 */
class CustomPermission extends Permission
{
    /** @var list<string> */
    public static array $events = [];

    protected static function booted(): void
    {
        static::created(fn (self $permission) => self::$events[] = 'created:'.$permission->name);
        static::updated(fn (self $permission) => self::$events[] = 'updated:'.$permission->getPrevious()['name'].'>'.$permission->name);
        static::deleted(fn (self $permission) => self::$events[] = 'deleted:'.$permission->name);
    }
}
