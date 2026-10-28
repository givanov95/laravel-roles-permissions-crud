<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return Authorizer::allows($this->user(), 'permissions', Authorizer::MANAGE)
            && (! $permission instanceof \Spatie\Permission\Contracts\Permission || EscalationGuard::canManagePermission($this->user(), $permission));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(Models::permissionClass(), 'name')->where('guard_name', Models::guard())->ignore($permissionId)],
        ];
    }
}
