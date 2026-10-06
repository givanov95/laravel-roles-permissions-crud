<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Http\Requests\Concerns\ChecksPermissionGrants;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    use ChecksPermissionGrants;

    public function authorize(): bool
    {
        $role = $this->route('role');

        return Authorizer::allows($this->user(), 'roles', Authorizer::MANAGE)
            && (! $role instanceof \Spatie\Permission\Models\Role || EscalationGuard::canManageRole($this->user(), $role));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roleId = $this->route('role')?->id;
        $table = config('permission.table_names.roles', 'roles');

        return [
            'name'          => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($roleId)],
            'permissions'   => ['array'],
            'permissions.*' => ['integer', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'id')->where('guard_name', config('roles-permissions-crud.guard', 'web'))],
        ];
    }
}
