# Changelog

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
