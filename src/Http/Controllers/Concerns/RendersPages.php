<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns;

use Inertia\Inertia;
use Inertia\Response;

/**
 * The Inertia pages of the screens: "{page_prefix}/roles/Index" and so on, each with the route name
 * prefix the Vue components resolve routes with, so it cannot drift from `route_name_prefix`.
 */
trait RendersPages
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function render(string $path, array $props = []): Response
    {
        return Inertia::render($this->page($path), [
            ...$props,
            'routeNamePrefix' => (string) config('roles-permissions-crud.route_name_prefix', 'admin.'),
        ]);
    }

    protected function page(string $path): string
    {
        $prefix = trim((string) config('roles-permissions-crud.page_prefix', 'admin'), '/');

        return $prefix === '' ? $path : $prefix.'/'.$path;
    }

    protected function routeName(string $name): string
    {
        return config('roles-permissions-crud.route_name_prefix', 'admin.').$name;
    }
}
