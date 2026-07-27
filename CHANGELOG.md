# Changelog

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
