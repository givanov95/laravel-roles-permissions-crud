<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Http\Requests\Concerns\ChecksPermissionGrants;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Contracts\Role as RoleContract;

class UpdateRoleRequest extends FormRequest
{
    use ChecksPermissionGrants;

    public function authorize(): bool
    {
        $role = $this->route('role');

        return Authorizer::allows($this->user(), 'roles', Authorizer::MANAGE)
            && (! $role instanceof \Spatie\Permission\Contracts\Role || EscalationGuard::canManageRole($this->user(), $role));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $role = $role instanceof Model && $role instanceof RoleContract ? $role : null;

        return [
            // Ignored as a model, so by its own key column (ignore() of a bare id looks at `id`).
            'name'          => ['required', 'string', 'max:255', Models::uniqueRoleName()->ignore($role), ...($this->input('name') === $role?->name ? [] : Models::reservedRoleNameRule())],
            'permissions'   => ['array'],
            // Whether each key exists in the guard is checked with the escalation checks (ChecksPermissionGrants).
            'permissions.*' => [Models::permissionKeyRule()],
        ];
    }
}
