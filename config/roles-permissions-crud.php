<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The guard name applied to roles and permissions created through this
    | package's CRUD. Matches spatie/laravel-permission's `guard_name`.
    |
    */
    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Authorization permissions
    |--------------------------------------------------------------------------
    |
    | The permission a user must have for each resource. Applied as `permission:`
    | route middleware by the Route::rolesPermissionsCrud() macro, and checked
    | again by the form requests and the controllers, so routes you register by
    | hand are covered too.
    |
    | A resource takes one of:
    |
    |   - a view/manage pair: `view` is needed to list, `manage` to open a form
    |     or change anything. `manage` does not include `view`; give both to
    |     whoever edits. A null part is not checked.
    |   - a string: one permission for reading and writing, as it was before
    |     they were split (`'roles' => 'view-roles'`).
    |   - null: nothing is checked, rely on your own route middleware.
    |
    */
    'authorize_permissions' => [
        'roles' => [
            'view'   => 'view-roles',
            'manage' => 'manage-roles',
        ],
        'permissions' => [
            'view'   => 'view-permissions',
            'manage' => 'manage-permissions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prevent privilege escalation
    |--------------------------------------------------------------------------
    |
    | While true, whoever may manage roles cannot hand out more than they hold:
    | a permission can be added to a role only by a user who holds it, a role
    | can be edited, deleted or opened for editing only by a user who holds
    | every permission it carries, the forms offer only the permissions the
    | user holds, a protected role cannot lose permissions, and a permission a
    | protected role holds cannot be renamed or deleted. Set to false only if
    | you enforce this yourself.
    |
    */
    'prevent_privilege_escalation' => true,

    /*
    |--------------------------------------------------------------------------
    | Protected roles
    |--------------------------------------------------------------------------
    |
    | Roles that gate access elsewhere in the app and therefore cannot be
    | renamed or deleted through the UI. Their permission set may still be
    | edited.
    |
    */
    'protected_roles' => [],

    /*
    |--------------------------------------------------------------------------
    | Protected permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that cannot be renamed or deleted through the UI. The ones named
    | in `authorize_permissions` are always protected, because removing them would
    | lock out whoever manages access; list any others your application depends on
    | here (for example the ones its routes check).
    |
    */
    'protected_permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Route registration (used by the Route::rolesPermissionsCrud() macro)
    |--------------------------------------------------------------------------
    |
    | - prefix:           URL prefix for the resource routes (e.g. /admin/roles)
    | - route_name_prefix: name prefix; controllers redirect to
    |                      "{route_name_prefix}roles.index" etc. Keep the
    |                      trailing dot.
    | - middleware:       middleware stack applied to the route group. The
    |                     per-resource `permission:` middleware is added on
    |                     top, from `authorize_permissions` above.
    |
    */
    'prefix' => 'admin',
    'route_name_prefix' => 'admin.',
    'middleware' => ['web', 'auth', 'verified'],

    /*
    |--------------------------------------------------------------------------
    | Inertia page prefix
    |--------------------------------------------------------------------------
    |
    | The controllers render "{page_prefix}/roles/Index", etc. Your app must
    | expose matching thin page shells (which wrap the package components in
    | your own layout) at resources/js/pages/{page_prefix}/...
    |
    */
    'page_prefix' => 'admin',
];
