<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission;

/**
 * A permission model with a UUID key in a `uuid` column, as spatie's docs set one up.
 */
class UuidPermission extends Permission
{
    use HasUuids;

    protected $primaryKey = 'uuid';
}
