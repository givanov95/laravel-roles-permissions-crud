export { default as RolesManager } from "./components/RolesManager.vue";
export { default as RoleForm } from "./components/RoleForm.vue";
export { default as PermissionsManager } from "./components/PermissionsManager.vue";
export { default as PermissionForm } from "./components/PermissionForm.vue";

// Self-contained UI primitives (exported in case a consumer wants to reuse them).
export { default as PrimaryButton } from "./components/ui/PrimaryButton.vue";
export { default as SecondaryButton } from "./components/ui/SecondaryButton.vue";
export { default as DangerButton } from "./components/ui/DangerButton.vue";
export { default as ConfirmationModal } from "./components/ui/ConfirmationModal.vue";

// Auth helpers ($hasRole / $can).
export { RolesPlugin } from "./plugins/RolesPlugin";
export { PermissionsPlugin } from "./plugins/PermissionsPlugin";

// Runtime config (translator + route resolver).
export { RolesPermissionsPlugin } from "./install";
export type { RolesPermissionsPluginOptions } from "./install";
export { setConfig, resetConfig, t, r, routeNamePrefix } from "./config";
export type { RolesPermissionsConfig, Translator, Router } from "./config";
