<?php

declare(strict_types=1);

namespace Givanov95\RolesPermissionsCrud\Http\Requests;

use Givanov95\RolesPermissionsCrud\Support\Authorizer;
use Givanov95\RolesPermissionsCrud\Support\Models;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Authorizer::allows($this->user(), 'permissions', Authorizer::MANAGE);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $table = config('permission.table_names.permissions', 'permissions');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique($table, 'name')->where('guard_name', Models::guard())],
        ];
    }
}
