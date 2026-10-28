# Changelog

## v6.3.0

### Fixed
- **Only the configured guard.** The lists show only the roles and permissions of
  `roles-permissions-crud.guard`. Editing, updating or deleting a role or permission of another
  guard is a 404; before, it could be renamed or deleted, and saving a role of another guard with
  this guard's permissions was a 500.
- A name has to be unique only within the guard: an `editor` role for `web` is accepted when there is
  one for `api`. The unique and exists rules use the configured models' table and connection.
- An empty `guard` falls back to the application's default guard (`auth.defaults.guard`).
- The configured permission model (`permission.models.permission`) is used everywhere, including the
  escalation checks, which still read spatie's own class after v6.2.0.
- **Renaming or deleting a permission respects escalation.** It is allowed only to a user who holds the
  permission, or when no role or user has it (403 otherwise). Before, anyone with
  `manage-permissions` could change roles and users beyond them this way. A permission in use can be
  renamed only to a name the user can use themselves, because the rename hands that name to everyone
  who has the permission.
- **Nobody locks themselves out.** A user cannot take away from their own role, or delete with it, a
  permission from `authorize_permissions` that they hold through nothing else.
- The error for a permission the user does not hold is `The selected permission is invalid.`; it no
  longer names the permissions, which let a user without `view-permissions` read names by id. An id
  that does not exist in the guard gets the same error, on `permissions` instead of `permissions.N`,
  so the error does not tell which ids exist.

### Changed
- **A role or permission in use is not deleted** (new `allow_delete_in_use`, `false` by default): a role
  assigned to anyone, or a permission given to a role or a user, gives an error toast instead. Set
  `allow_delete_in_use` to `true` to keep the old behaviour.

## v6.2.0

### Added
- **The configured models are used.** `RoleController` and `PermissionController` create, update
  and delete through the classes in `permission.models.role` / `permission.models.permission`
  instead of spatie's own, so an application model that extends them (observers, overridden
  methods) sees the changes made in these screens. The routes still bind spatie's class, so they
  keep working when cached; the bound model is switched to the configured class without another
  query. With spatie's default models nothing changes.

### Changed
- `store`, `update` and `destroy` run in a database transaction: a role is not left behind, and
  keeps its old name, when syncing its permissions fails.

## v6.1.0

### Added
- `spatie/laravel-permission` ^8.0 is allowed (`^6.0|^7.0|^8.0`). The package's tests and PHPStan
  pass against spatie 8.3; nothing in the package needed to change.

## v6.0.0

### Breaking
- **Reading and writing are separate permissions.** `authorize_permissions` now defaults to a
  `view`/`manage` pair per resource: `view-roles` / `manage-roles` and `view-permissions` /
  `manage-permissions`. `view` lists; `manage` opens a form or changes anything (create, store,
  edit, update, destroy) and does not include `view`, so give both to whoever edits. Before, one
  permission (`view-roles`) guarded both, which made it equal to full access.
- **Privilege escalation is prevented by default** (`prevent_privilege_escalation`, new). Whoever
  manages roles can add a permission to a role only if they hold it, can open, edit or delete a
  role only if they hold every permission it carries, a protected role cannot lose permissions,
  and a permission a protected role holds cannot be renamed or deleted. A user "holds" what they
  can do, so a super admin let through by `Gate::before` holds everything. The roles index sends
  `can_manage` for each role, the role forms offer only the permissions the user holds, and
  `RolesManager` hides edit and delete for roles the user cannot manage.

  **Upgrade:** create the `manage-roles` and `manage-permissions` permissions and give them to
  whoever manages access, or keep the old behaviour by setting a string in your published config
  (`'roles' => 'view-roles', 'permissions' => 'view-permissions'`). Set
  `prevent_privilege_escalation` to `false` to turn the new checks off. Bump
  `@givanov95/vue-roles-permissions-crud` together with the composer package.

### Added
- `authorize_permissions` also accepts a string (one permission for reading and writing) or
  `null`, per resource; inside a pair a null part is not checked.
- `prevent_privilege_escalation` config key.

### Security
- The controllers now check `authorize_permissions` on every action (index, create,
  edit and destroy were unchecked; store and update were checked only by their form
  requests). Routes registered by hand instead of with `Route::rolesPermissionsCrud()`
  were open to every logged-in user. A `null` value still skips the check.
- Permissions can be protected like roles. `protected_permissions` (new, empty by default)
  lists permissions that cannot be renamed or deleted, and the permissions named in
  `authorize_permissions` are always protected: **behaviour change**, `view-roles` and
  `view-permissions` can no longer be deleted or renamed through the UI. A protected
  permission is reported with `is_protected` on the permissions index and edit pages, and
  the Vue components hide its delete button and lock its name.
- The `permissions.*` rule on roles only accepts permissions of the configured `guard`; one
  from another guard used to end in an unhandled `PermissionDoesNotExist` (a 500).

### Changed
- The npm package's `@inertiajs/vue3` peer dependency is now `^3.0.0`, matching the
  `inertiajs/inertia-laravel ^3.0` that `composer.json` already requires (it allowed
  Inertia 1 and 2 before).

### Added
- CI: PHPUnit and PHPStan on PHP 8.3 and 8.4 for every push to `main` and every pull
  request. Publishing to npm now waits for it.
- Tests that run with the default `middleware` and `authorize_permissions`: access
  control, validation, protected roles, mass assignment and the permission cache.
- README: a Security section describing what the defaults protect, and a Tests section.

### Removed
- The outdated README sentence "until the packages are published, link them locally".

## v5.1.0

### Changed
- The npm package's `RoleForm` now picks permissions with a plain checkbox list
  instead of the `@givanov95/vue-forms` multi `Select`. The prop contract
  (`permissions: { value, label }[]`) is unchanged.

### Removed
- `@givanov95/vue-forms` peer dependency. `@givanov95/vue-forms` (retired) no longer
  needs to be installed or listed in `ssr.noExternal`.

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
