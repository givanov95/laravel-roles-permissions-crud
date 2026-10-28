<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Lists;

use Givanov95\DataTable\DataTable;
use Givanov95\DataTable\Ordering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A list the screens page, search and sort on the server (givanov95/laravel-data-table). The payload is
 * `DataTable::toArray()` with the rows sent through {@see row()} only, so no model attribute leaves
 * the server unless a list names it.
 *
 * An application changes a list by binding its own subclass in the container:
 * `$this->app->bind(RoleList::class, AppRoleList::class)`.
 */
abstract class ServerList
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $this->prepare($request);
        $table = $this->table($request);

        // The request's sort when it names an orderable column, else the default one, ascending: the
        // package's own default is the key, newest first, and these lists read best by name.
        $table->setOrdering(Ordering::fromRequest($request, $this->defaultSort(), 'ASC'));
        $table->process();

        $payload = $table->toArray();
        $payload['data'] = $table->getData()->map(fn (Model $model): array => $this->row($model, $request))->values()->all();

        return $payload;
    }

    /**
     * The table with its query and columns. The archive stays off.
     *
     * @return DataTable<Model>
     */
    abstract protected function table(Request $request): DataTable;

    /**
     * What a row sends to the page.
     *
     * @return array<string, mixed>
     */
    abstract protected function row(Model $model, Request $request): array;

    /**
     * Work out, once per payload, what every row needs (the user's permissions and the like). Values kept
     * on the list are set here, so a list bound as a singleton does not carry them over to another user.
     */
    protected function prepare(Request $request): void {}

    protected function defaultSort(): string
    {
        return 'name';
    }
}
