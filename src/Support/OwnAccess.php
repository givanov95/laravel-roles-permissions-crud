<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;

/**
 * The roles a user has (with their permissions) and the permissions given to them directly, in the
 * package's guard: what {@see EscalationGuard::locksOut()} needs. Read once per request
 * ({@see EscalationGuard::ownAccess()}) instead of once for each of the user's roles that is checked.
 */
final readonly class OwnAccess
{
    /**
     * @param  Collection<int, RoleContract>  $roles
     * @param  Collection<int, string>  $direct
     */
    public function __construct(
        public Collection $roles,
        public Collection $direct,
    ) {}

    /**
     * Nothing to weigh: escalation is not prevented, or the user has no roles of their own.
     */
    public static function none(): self
    {
        return new self(collect(), collect());
    }

    public function includes(RoleContract $role): bool
    {
        return $this->roles->contains(fn (RoleContract $own) => $own->getKey() === $role->getKey());
    }
}
