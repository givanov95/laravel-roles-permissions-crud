/**
 * Runtime configuration for the roles-permissions-crud package.
 *
 * The components depend on a translation helper (historically the global
 * `__()`) and ziggy's `route()`. The package does not assume either global
 * exists: consumers register their own implementations through the Vue plugin
 * (see install.ts), or let the fallbacks below take over (identity translation,
 * global lookup).
 */

export type Translator = (key: string, replacements?: Record<string, unknown>) => string;

export type Router = (...args: unknown[]) => unknown;

export interface RolesPermissionsConfig {
    translator: Translator;
    route: Router | null;
    /** Name prefix used to resolve route names, e.g. "admin." -> admin.roles.index */
    routeNamePrefix: string;
}

const defaultTranslator: Translator = (key) => key;

const defaults: RolesPermissionsConfig = {
    translator: defaultTranslator,
    route: null,
    routeNamePrefix: "admin.",
};

const state: RolesPermissionsConfig = { ...defaults };

export function setConfig(partial: Partial<RolesPermissionsConfig>): void {
    if (partial.translator) state.translator = partial.translator;
    if (partial.route !== undefined) state.route = partial.route;
    if (typeof partial.routeNamePrefix === "string") {
        state.routeNamePrefix = partial.routeNamePrefix;
    }
}

/** Restore every config value to its built-in default. Useful for tests. */
export function resetConfig(): void {
    state.translator = defaults.translator;
    state.route = defaults.route;
    state.routeNamePrefix = defaults.routeNamePrefix;
}

/** Translate a key. */
export function t(key: string, replacements?: Record<string, unknown>): string {
    if (state.translator !== defaultTranslator) {
        return state.translator(key, replacements);
    }

    const globalT = (globalThis as any).__;
    if (typeof globalT === "function") {
        return globalT(key, replacements);
    }

    return key;
}

/** Resolve a route. Accepts a bare name ("roles.index") which gets the
 * configured prefix applied, or a fully-qualified name (containing a dot the
 * caller controls) passed straight through. */
export function r(name: string, ...args: unknown[]): unknown {
    const fn = state.route ?? (globalThis as any).route;

    if (typeof fn !== "function") {
        return undefined;
    }

    return fn(state.routeNamePrefix + name, ...args);
}

export function routeNamePrefix(): string {
    return state.routeNamePrefix;
}
