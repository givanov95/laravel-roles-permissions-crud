<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
        $table = config('permission.table_names.roles', 'roles');

        return [
            'name'          => ['required', 'string', 'max:255', 'unique:'.$table.',name'],
            'permissions'   => ['array'],
            'permissions.*' => ['integer', 'exists:'.config('permission.table_names.permissions', 'permissions').',id'],
        ];
    }
}
