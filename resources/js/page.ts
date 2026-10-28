import { usePage } from "@inertiajs/vue3";

import { setConfig } from "./config";

/**
 * What the server sends with every page of the screens. Call from a component's setup.
 *
 * The route name prefix comes from `route_name_prefix`, so it cannot drift from the PHP config; the
 * `routeNamePrefix` plugin option is only the fallback for pages that do not send it.
 */
export function useServerPage(): { canCreate: boolean } {
    const props = (usePage().props ?? {}) as Record<string, any>;

    if (typeof props.routeNamePrefix === "string") {
        setConfig({ routeNamePrefix: props.routeNamePrefix });
    }

    // Without `can` (a page of an older server) the button stays, as before.
    return { canCreate: props.can?.create ?? true };
}
