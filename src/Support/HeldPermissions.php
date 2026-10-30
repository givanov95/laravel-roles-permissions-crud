<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Support;

use Closure;
use Illuminate\Http\Request;
use WeakMap;

/**
 * What each user holds, and their own roles and permissions, remembered for one request: a form request
 * and its controller both ask, a list asks for every row, and each answer costs queries.
 *
 * Bound as scoped, so a queue worker or Octane starts every job or request empty, and keyed by the
 * request object, so a new request (as in tests, which reuse the application) never sees an old answer.
 */
final class HeldPermissions
{
    /** @var WeakMap<Request, array<string, mixed>> */
    private WeakMap $byRequest;

    public function __construct()
    {
        $this->byRequest = new WeakMap;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $resolve
     * @return T
     */
    public function remember(Request $request, string $key, Closure $resolve): mixed
    {
        $cached = $this->byRequest[$request] ?? [];

        if (! array_key_exists($key, $cached)) {
            $cached[$key] = $resolve();
            $this->byRequest[$request] = $cached;
        }

        return $cached[$key];
    }
}
