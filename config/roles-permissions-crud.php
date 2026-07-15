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
    | The permission a user must have for each resource. Checked by the
    | package FormRequests, and applied as `permission:` route middleware by
    | the Route::rolesPermissionsCrud() macro. Set a value to null to skip
    | the check for that resource (rely only on your own route middleware).
    |
    */
    'authorize_permissions' => [
        'roles'       => 'manage-roles',
        'permissions' => 'manage-permissions',
    ],

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
