import type { App } from "vue";
import { usePage } from "@inertiajs/vue3";

/**
 * Registers a global `$hasRole(role | role[])` helper that reads the current
 * user's roles from Inertia shared props (auth.user.roles).
 */
export const RolesPlugin = {
    install: (app: App) => {
        app.config.globalProperties.$hasRole = (rolesCheck: string | Array<string>) => {
            const userRoles = (usePage().props as { auth: { user: { roles: string[] } } }).auth.user.roles;

            return typeof rolesCheck === "string"
                ? userRoles.includes(rolesCheck)
                : userRoles.some((element) => rolesCheck.includes(element));
        };
    },
};

export default RolesPlugin;
