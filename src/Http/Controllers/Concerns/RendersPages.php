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
     * The package's own page data goes under one `rolesPermissionsCrud` prop, so it cannot shadow a prop
     * the application shares (a `can` for its layout, say).
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $crud
     */
    protected function render(string $path, array $props = [], array $crud = []): Response
    {
        return Inertia::render($this->page($path), [
            ...$props,
            'rolesPermissionsCrud' => [
                'routeNamePrefix' => (string) config('roles-permissions-crud.route_name_prefix', 'admin.'),
                ...$crud,
            ],
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
