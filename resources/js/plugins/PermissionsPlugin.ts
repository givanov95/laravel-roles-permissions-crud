import type { App } from "vue";
import { usePage } from "@inertiajs/vue3";

/**
 * Registers a global `$can(permission)` helper that reads the current user's
 * permissions from Inertia shared props (auth.user.permissions).
 */
export const PermissionsPlugin = {
    install: (app: App) => {
        app.config.globalProperties.$can = (permission: string) => {
            // Guests have auth.user = null — treat them as having no permissions.
            const props = usePage().props as unknown as { auth?: { user?: { permissions?: string[] } | null } };
            const permissions = props.auth?.user?.permissions ?? [];

            return permissions.includes(permission);
        };
    },
};

export default PermissionsPlugin;
