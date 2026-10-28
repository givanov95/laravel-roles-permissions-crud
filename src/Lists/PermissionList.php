<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Lists;

use Givanov95\DataTable\DataTable;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Permission\Contracts\Permission;

/**
 * The permissions of the package's guard: searched and sorted by name, sorted by the number of roles.
 */
class PermissionList extends ServerList
{
    /** @var array<int, string>|null */
    private ?array $protected = null;

    protected function table(Request $request): DataTable
    {
        return (new DataTable($this->query(), $request))
            ->setColumn('name', __('Name'), searchable: true, orderable: true)
            ->setColumn('roles_count', __('Roles'), orderable: true);
    }

    /**
     * @return Builder<\Spatie\Permission\Models\Permission>
     */
    protected function query(): Builder
    {
        return Models::permissions()->withCount('roles');
    }

    protected function row(Model $model, Request $request): array
    {
        /** @var Model&Permission $model */
        $this->protected ??= EscalationGuard::protectedPermissionNames();

        return [
            'id'           => $model->getKey(),
            'name'         => $model->name,
            'roles_count'  => (int) $model->getAttribute('roles_count'),
            'is_protected' => in_array($model->name, $this->protected, true),
        ];
    }
}
