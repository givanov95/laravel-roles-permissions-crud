<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
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
        $permissionId = $this->route('permission')?->id;
        $table = config('permission.table_names.permissions', 'permissions');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->ignore($permissionId)],
        ];
    }
}
