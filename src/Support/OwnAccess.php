<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Role as RoleContract;

/**
 * The roles a user has (with their permissions) and the permissions given to them directly, in the
 * package's guard: what {@see EscalationGuard::locksOut()} needs. A list makes one for the page and
 * passes it to every row instead of reading them once for each of the user's roles in it.
 *
 * Read when first needed, from the database: the roles (one query) at the first {@see includes()}, and
 * their permissions and the direct ones (two queries) at the first of the user's roles that takes a
 * guarding permission away. So a page without the user's roles reads only those, and without wildcards
 * a page whose rows take no guarding permission away reads nothing.
 */
final class OwnAccess
{
    /** @var EloquentCollection<int, Model&RoleContract>|null */
    private ?EloquentCollection $roles = null;

    /** @var Collection<int, string> */
    private Collection $direct;

    /** Whether the roles' permissions and the direct ones are read. */
    private bool $weighed = false;

    /**
     * @param  BelongsToMany<Model&RoleContract, Model>|null  $rolesQuery
     * @param  BelongsToMany<Model, Model>|null  $directQuery
     */
    public function __construct(
        private readonly ?BelongsToMany $rolesQuery,
        private readonly ?BelongsToMany $directQuery,
    ) {}

    /**
     * Nothing to weigh: escalation is not prevented, the user has no roles and permissions of their
     * own, or the caller knows the role is not theirs.
     */
    public static function none(): self
    {
        return new self(null, null);
    }

    public function includes(RoleContract $role): bool
    {
        return $this->roles()->contains(fn (RoleContract $own) => self::same($own, $role));
    }

    /**
     * The names of the permissions the user holds other than through the role: directly or through
     * their other roles.
     *
     * @return Collection<int, string>
     */
    public function heldWithout(RoleContract $role): Collection
    {
        $roles = $this->roles();

        if (! $this->weighed) {
            $roles->load('permissions:'.Models::permissionKeyName().',name');
            $this->direct = $this->directQuery?->pluck('name') ?? collect();
            $this->weighed = true;
        }

        return $this->direct->merge($roles
            ->reject(fn (RoleContract $own) => self::same($own, $role))
            ->flatMap(fn (RoleContract $own) => $own->permissions->pluck('name')));
    }

    /**
     * @return EloquentCollection<int, Model&RoleContract>
     */
    private function roles(): EloquentCollection
    {
        return $this->roles ??= $this->rolesQuery?->get() ?? new EloquentCollection;
    }

    /**
     * Keys compared as strings: the same id may come as an int from one source and a string from
     * another, and a miss here would skip the lock-out check.
     */
    private static function same(RoleContract $one, RoleContract $other): bool
    {
        return (string) $one->getKey() === (string) $other->getKey();
    }
}
