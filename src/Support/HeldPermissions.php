<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use WeakMap;

/**
 * What each user holds, remembered for one request: a form request and its controller both ask, and
 * each answer is a query plus a gate check for every permission.
 *
 * Bound as scoped, so a queue worker or Octane starts every job or request empty, and keyed by the
 * request object, so a new request (as in tests, which reuse the application) never sees an old answer.
 */
final class HeldPermissions
{
    /** @var WeakMap<Request, array<string, Collection<int, int>>> */
    private WeakMap $byRequest;

    public function __construct()
    {
        $this->byRequest = new WeakMap;
    }

    /**
     * @param  Closure(): Collection<int, int>  $resolve
     * @return Collection<int, int>
     */
    public function remember(Request $request, string $key, Closure $resolve): Collection
    {
        $cached = $this->byRequest[$request] ?? [];

        if (! array_key_exists($key, $cached)) {
            $cached[$key] = $resolve();
            $this->byRequest[$request] = $cached;
        }

        return $cached[$key];
    }
}
