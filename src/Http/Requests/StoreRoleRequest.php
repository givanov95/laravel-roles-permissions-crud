<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Http\Requests\Concerns\ChecksPermissionGrants;
use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    use ChecksPermissionGrants;

    public function authorize(): bool
    {
        return Authorizer::allows($this->user(), 'roles', Authorizer::MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {

        return [
            'name'          => ['required', 'string', 'max:255', Models::uniqueRoleName()],
            'permissions'   => ['array'],
            // Whether each id exists in the guard is checked with the escalation checks (ChecksPermissionGrants).
            'permissions.*' => ['integer'],
        ];
    }
}
