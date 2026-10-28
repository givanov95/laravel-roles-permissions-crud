<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests\Concerns;

use Givanov95\RolesPermissionsCrud\Support\EscalationGuard;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Contracts\Validation\Validator;
use Spatie\Permission\Contracts\Role;

/**
 * The checks on a submitted role beyond the ordinary rules: a protected role keeps its name, and the
 * permission checks, which run only once the ordinary rules have passed.
 */
trait ChecksPermissionGrants
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $role = $this->route('role');
            $role = $role instanceof Role ? $role : null;

            // A protected role keeps its name; say so instead of saving the rest and dropping the name.
            if ($role !== null && ! $validator->errors()->has('name') && EscalationGuard::isProtectedRole($role) && $this->input('name') !== $role->name) {
                $validator->errors()->add('name', __('This role is protected; its name cannot be changed.'));
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $requested = collect((array) $this->input('permissions', []))->map(fn (mixed $id) => (int) $id);

            $unknown = $requested->diff(Models::permissions()->whereIn('id', $requested)->pluck('id'));
            $beyond = EscalationGuard::beyondTheUser($this->user(), $role, $requested);

            // One message, without names, for an id that does not exist in the guard and one the user may
            // not grant: otherwise the error would tell a user who may not list the permissions which ids
            // exist and what they are called.
            if ($unknown->isNotEmpty() || $beyond->isNotEmpty()) {
                $validator->errors()->add('permissions', __('The selected permission is invalid.'));

                return;
            }

            if (EscalationGuard::stripsProtectedRole($role, $requested)) {
                $validator->errors()->add('permissions', __('The :role role is protected and cannot lose permissions.', [
                    'role' => $role?->name,
                ]));

                return;
            }

            $lost = $role === null ? collect() : EscalationGuard::locksOut($this->user(), $role, $requested);

            if ($lost->isNotEmpty()) {
                $validator->errors()->add('permissions', __('You cannot take :permissions away from your own role, you need them to manage access.', [
                    'permissions' => $lost->implode(', '),
                ]));
            }
        });
    }
}
