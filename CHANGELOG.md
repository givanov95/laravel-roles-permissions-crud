# Changelog

## v5.0.0

### Breaking
- `RoleController` now shares the `permissions` prop on `Admin/Roles/Create` and
  `Admin/Roles/Edit` as `{ value, label }` options (was `{ name, value }`), the
  same contract as `MultiSelect` / `RadioOptions` / `Combobox` in the starter
  kit and the `HasOptions` enum trait.
- The npm package's `RoleForm` expects the new shape too
  (`permissions: { value: number; label: string }[]`).

  **Upgrade:** bump both `givanov95/laravel-roles-permissions-crud` and
  `@givanov95/vue-roles-permissions-crud` to ^5.0. Custom Create/Edit pages
  that map `permissions` client-side (`{ value, label: permission.name }`) can
  pass the prop straight to `MultiSelect` and drop the mapping.

## v4.0.1

### Fixed
- `RoleController::update()` and `PermissionController::update()` now redirect
  back to their own `edit` route instead of `index`, so the admin stays on the
  record they just saved. The matching `RoleForm`/`PermissionForm` submit calls
  now pass `preserveState: 'errors'`, so a successful save remounts the page
  from fresh server props (validation errors still preserve the form state).
  No action needed from consumers.

### Added
- A minimal Testbench feature-test suite (`composer test`) covering the
  store/update/destroy redirects on both controllers.

## v4.0.0

### Breaking
- Success/error toasts are now emitted via `Inertia::flash('toast', …)` (native
  Inertia page-level flash) instead of `redirect()->with('success'/'error', …)`.
  That is what the Vue starter kit's `flashToast` composable actually reads
  (`router.on('flash')` → page-level flash, one-time and cache-safe), so
  role/permission CRUD toasts now show — the old `->with(...)` only populated
  `props.flash`, which the listener never read.
- The package now requires `inertiajs/inertia-laravel: ^3.0` (dropped ^1/^2),
  since `Inertia::flash()` is a v3 feature.

  **Upgrade:** be on inertia-laravel ^3, and make sure a flash-toast listener
  reads the page-level `flash` (the kit's `flashToast` does).

## v3.0.0

### Breaking
- The default `authorize_permissions` were renamed from `manage-roles` /
  `manage-permissions` to `view-roles` / `view-permissions`, to align with the
  `{action}-{resource}` permission convention.

  **Upgrade:** rename the seeded permissions — and any `can()` checks /
  `permission:` middleware — from `manage-roles` / `manage-permissions` to
  `view-roles` / `view-permissions`. To keep the old names instead, publish the
  config (`php artisan vendor:publish --tag=roles-permissions-crud-config`) and
  set `authorize_permissions` back to `manage-roles` / `manage-permissions`.
