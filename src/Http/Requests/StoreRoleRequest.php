<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = config('roles-permissions-crud.authorize_permissions.roles');

        return $permission === null || ($this->user()?->can($permission) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = config('permission.table_names.roles', 'roles');

        return [
            'name'          => ['required', 'string', 'max:255', 'unique:'.$table.',name'],
            'permissions'   => ['array'],
            'permissions.*' => ['integer', Rule::exists(config('permission.table_names.permissions', 'permissions'), 'id')->where('guard_name', config('roles-permissions-crud.guard', 'web'))],
        ];
    }
}
