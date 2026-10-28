<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * After a delete from a list, back to the list as the user left it: the page, search and sort are in
 * its query string, which a plain redirect to the index route would drop.
 */
trait RedirectsToIndex
{
    abstract protected function routeName(string $name): string;

    protected function redirectToIndex(string $resource): RedirectResponse
    {
        $index = route($this->routeName($resource.'.index'));
        $previous = url()->previous();

        return redirect()->to(str_starts_with($previous, $index.'?') ? $previous : $index);
    }
}
