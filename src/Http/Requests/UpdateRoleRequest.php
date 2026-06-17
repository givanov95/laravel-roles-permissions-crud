<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = config('roles-permissions-crud.authorize_role');

        return $role === null || ($this->user()?->hasRole($role) ?? false);
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
            'permissions.*' => ['integer', 'exists:'.config('permission.table_names.permissions', 'permissions').',id'],
        ];
    }
}
