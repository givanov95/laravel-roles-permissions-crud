<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermissionRequest extends FormRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $permission = $this->route('permission');

            if (! $permission instanceof \Spatie\Permission\Contracts\Permission) {
                return;
            }

            // A protected permission keeps its name; say so instead of reporting a save that did nothing.
            if ($this->input('name') !== $permission->name && in_array($permission->name, EscalationGuard::protectedPermissionNames(), true)) {
                $validator->errors()->add('name', __('This permission is protected; its name cannot be changed.'));
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! EscalationGuard::canRenamePermission($this->user(), $permission, (string) $this->input('name'))) {
                $validator->errors()->add('name', __('A permission in use can be renamed only to one you hold yourself.'));
            }
        });
    }

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
