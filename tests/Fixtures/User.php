<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Tests\Fixtures;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Stands in for the application's user: the `verified` middleware only does anything for a
 * user that implements MustVerifyEmail, and the guards need HasRoles.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];
}
