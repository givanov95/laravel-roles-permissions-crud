<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Fixtures;

use Spatie\Permission\Models\Role;

/**
 * An application's own role model, as configured in `permission.models.role`. It notes its model
 * events, and can be told to fail a permission sync.
 */
class CustomRole extends Role
{
    /** @var list<string> */
    public static array $events = [];

    public static bool $failSync = false;

    protected static function booted(): void
    {
        static::created(fn (self $role) => self::$events[] = 'created:'.$role->name);
        static::updated(fn (self $role) => self::$events[] = 'updated:'.$role->getPrevious()['name'].'>'.$role->name);
        static::deleted(fn (self $role) => self::$events[] = 'deleted:'.$role->name);
    }

    public function syncPermissions(...$permissions): static
    {
        if (self::$failSync) {
            throw new \RuntimeException('The permission sync failed.');
        }

        self::$events[] = 'synced:'.$this->name;

        return parent::syncPermissions(...$permissions);
    }
}
