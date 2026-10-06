# laravel-roles-permissions-crud

Admin CRUD for [spatie/laravel-permission](https://github.com/spatie/laravel-permission)
roles & permissions on **Laravel + Inertia**, with a matching **Vue 3** frontend.

The PHP package (`givanov95/laravel-roles-permissions-crud`) ships the controllers,
form requests and a route macro. The npm package (`@givanov95/vue-roles-permissions-crud`)
ships the Vue components you drop into thin page shells inside your own admin layout.

## Why thin shells?

Full Inertia pages cannot be resolved from a package (the Inertia resolver only globs
the host app's `resources/js/pages`). So — exactly like `@givanov95/vue-data-table` —
the package ships **components**; each app keeps a ~10-line page shell that wraps the
component in its own layout. The shared logic lives in the package; only the layout
stays local.

## Install

```bash
composer require givanov95/laravel-roles-permissions-crud
npm install @givanov95/vue-roles-permissions-crud
```

### Local development (path / file: link)

To work on the package alongside an application, link both from the application instead of installing the released versions.

`composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../laravel-roles-permissions-crud" }
],
"require": {
    "givanov95/laravel-roles-permissions-crud": "*"
}
```

`package.json`:

```json
"dependencies": {
    "@givanov95/vue-roles-permissions-crud": "file:../laravel-roles-permissions-crud"
}
```

Vite must bundle the package source (it ships `.ts`/`.vue`, not a build):

```js
// vite.config.ts — ssr.noExternal
noExternal: ['@givanov95/vue-roles-permissions-crud']
```

## Backend wiring

1. (Optional) publish & tune the config:

   ```bash
   php artisan vendor:publish --tag=roles-permissions-crud-config
   ```

   Keys: `guard`, `authorize_permissions`, `prevent_privilege_escalation`,
   `protected_roles`, `protected_permissions`, `prefix`, `route_name_prefix`,
   `middleware`, `page_prefix`. Defaults gate the admin area by four permissions
   (`/admin/roles`, route names `admin.roles.*`, Inertia pages `Admin/Roles/*`):
   `view-roles` and `view-permissions` to look, `manage-roles` and
   `manage-permissions` to change. Seed them and grant them to your admin role;
   whoever edits needs both the `view-*` and the `manage-*` permission.

2. Register the routes in `routes/web.php`:

   ```php
   Route::rolesPermissionsCrud();
   ```

   This creates `admin.roles.*` and `admin.permissions.*` resource routes (minus
   `show`) under the configured prefix and middleware, with per-resource
   `permission:` middleware from `authorize_permissions`.

## Security

What an application gets without configuring anything:

- The routes sit behind the configured `middleware` (`web`, `auth`, `verified` by default). Listing needs `view-roles` / `view-permissions`; opening a form or changing anything needs `manage-roles` / `manage-permissions` (`authorize_permissions`, see below). The form requests and the controllers check the same permissions themselves on every action, so routes you register by hand instead of with the macro are covered too.
- `permission` is a middleware alias that spatie/laravel-permission does not register for you. Register it in your application (Laravel 11+: `$middleware->alias(['permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class])` in `bootstrap/app.php`), otherwise the routes cannot resolve it and requests fail.
- `authorize_permissions` takes, per resource, a `['view' => ..., 'manage' => ...]` pair (the default), a string (one permission for reading and writing, as before they were split) or `null`. A null part is not checked, and `null` drops the check everywhere (route middleware, form requests and controllers), which leaves only `middleware`; do that only when you guard the routes yourself.
- **Nobody can hand out more than they hold** (`prevent_privilege_escalation`, on by default). A permission can be added to a role only by a user who holds it; a role can be opened for editing, edited or deleted only by a user who holds every permission it carries; the forms offer only the permissions the user holds, and the roles list says which roles are theirs to change (`can_manage`). A user counts as holding what they can do, so a super admin let through by `Gate::before` holds everything.
- `protected_roles` (empty by default; list the roles your application's access depends on, e.g. `['Administrator']`) cannot be renamed or deleted and, while escalation is prevented, cannot lose permissions.
- `protected_permissions` (empty by default) lists permissions that cannot be renamed or deleted. The ones named in `authorize_permissions` are always protected, because deleting one would lock out whoever manages access, and so, while escalation is prevented, are the permissions a protected role holds. Add the permissions your own routes check.
- A permission given to a role must exist for the configured `guard`; one from another guard is a validation error.

## Frontend wiring

1. Register the runtime config once in your app entrypoint:

   ```ts
   import { RolesPermissionsPlugin, RolesPlugin, PermissionsPlugin } from '@givanov95/vue-roles-permissions-crud';

   app.use(RolesPermissionsPlugin, {
       translator: (key, replacements) => (window as any).__(key, replacements),
       route: (...args) => (window as any).route(...args),
       routeNamePrefix: 'admin.', // must match config('roles-permissions-crud.route_name_prefix')
   });
   // Optional global $hasRole / $can helpers:
   app.use(RolesPlugin).use(PermissionsPlugin);
   ```

2. Add thin page shells under `resources/js/pages/Admin/`:

   ```vue
   <!-- Admin/Roles/Index.vue -->
   <script setup lang="ts">
   import { RolesManager } from '@givanov95/vue-roles-permissions-crud';
   import AdminLayout from '@/layouts/AdminLayout.vue';
   defineProps<{ roles: any[] }>();
   </script>
   <template>
       <AdminLayout :title="__('Roles')">
           <template #header><h1 class="text-xl font-semibold text-gray-800">{{ __('Roles') }}</h1></template>
           <RolesManager :roles="roles" />
       </AdminLayout>
   </template>
   ```

   Components & the props each shell forwards:

   | Page                       | Component             | Props                          |
   | -------------------------- | --------------------- | ------------------------------ |
   | `Admin/Roles/Index`        | `RolesManager`        | `roles`                        |
   | `Admin/Roles/Create`       | `RoleForm`            | `permissions`                  |
   | `Admin/Roles/Edit`         | `RoleForm`            | `role`, `permissions`          |
   | `Admin/Permissions/Index`  | `PermissionsManager`  | `permissions`                  |
   | `Admin/Permissions/Create` | `PermissionForm`      | —                              |
   | `Admin/Permissions/Edit`   | `PermissionForm`      | `permission`                   |

## Tests

```bash
composer test      # PHPUnit on Orchestra Testbench
composer analyse   # PHPStan (Larastan)
```

`TestCase` switches the middleware, `authorize_permissions` and `prevent_privilege_escalation` off so the controllers can be tested on their own; `DefaultsTestCase` runs with the package defaults (access control, validation, protected roles, mass assignment, the permission cache). CI runs both commands on PHP 8.3 and 8.4 for every push to `main` and every pull request, and publishing to npm waits for it. It uses the newest Laravel that `composer.json` allows; Laravel 11 and 12 are not tested separately.

## License

MIT © Georgi Ivanov
