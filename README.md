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

Until the packages are published, link them locally.

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
noExternal: ['@givanov95/vue-roles-permissions-crud', '@givanov95/vue-forms']
```

## Backend wiring

1. (Optional) publish & tune the config:

   ```bash
   php artisan vendor:publish --tag=roles-permissions-crud-config
   ```

   Keys: `guard`, `authorize_role`, `protected_roles`, `prefix`, `route_name_prefix`,
   `middleware`, `page_prefix`. Defaults assume a super-admin admin area
   (`/admin/roles`, route names `admin.roles.*`, Inertia pages `Admin/Roles/*`).

2. Register the routes in `routes/web.php`:

   ```php
   Route::rolesPermissionsCrud();
   ```

   This creates `admin.roles.*` and `admin.permissions.*` resource routes (minus
   `show`) under the configured prefix and middleware.

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

## License

MIT © Georgi Ivanov
