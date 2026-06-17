import type { App, Plugin } from "vue";

import { setConfig, type RolesPermissionsConfig } from "./config";

export interface RolesPermissionsPluginOptions extends Partial<RolesPermissionsConfig> {}

/**
 * Registers the package's runtime config (translator, route resolver, route
 * name prefix). Use once in your app entrypoint:
 *
 *   app.use(RolesPermissionsPlugin, {
 *       translator: (key, r) => __(key, r),
 *       route: (...args) => route(...args),
 *       routeNamePrefix: 'admin.',
 *   })
 */
export const RolesPermissionsPlugin: Plugin = {
    install(_app: App, options: RolesPermissionsPluginOptions = {}) {
        setConfig(options);
    },
};
