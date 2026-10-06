<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests\Concerns;

use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Illuminate\Contracts\Validation\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The checks on the permissions submitted for a role, done after the ordinary rules have passed.
 */
trait ChecksPermissionGrants
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $role = $this->route('role');
            $role = $role instanceof Role ? $role : null;
            $requested = collect((array) $this->input('permissions', []))->map(fn (mixed $id) => (int) $id);

            $beyond = EscalationGuard::beyondTheUser($this->user(), $role, $requested);

            if ($beyond->isNotEmpty()) {
                $validator->errors()->add('permissions', __('You cannot grant permissions you do not hold: :permissions.', [
                    'permissions' => Permission::query()->whereIn('id', $beyond)->orderBy('name')->pluck('name')->implode(', '),
                ]));

                return;
            }

            if (EscalationGuard::stripsProtectedRole($role, $requested)) {
                $validator->errors()->add('permissions', __('The :role role is protected and cannot lose permissions.', [
                    'role' => $role?->name,
                ]));
            }
        });
    }
}
