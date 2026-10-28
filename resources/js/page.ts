import { usePage } from "@inertiajs/vue3";

/**
 * The package's own data on a page of the screens (`rolesPermissionsCrud`), kept under one prop so it
 * cannot shadow a prop the application shares.
 */
export interface ServerPageData {
    routeNamePrefix?: string;
    canCreate?: boolean;
}

export function serverPageData(): ServerPageData {
    try {
        const data = (usePage().props as Record<string, unknown> | undefined)?.rolesPermissionsCrud;

        return typeof data === "object" && data !== null ? (data as ServerPageData) : {};
    } catch {
        // Outside an Inertia app (a test, a storybook): nothing from the server.
        return {};
    }
}

/**
 * What a list needs from the server. Without it (a page of an older server) the Add button stays, as
 * before.
 */
export function useServerPage(): { canCreate: boolean } {
    return { canCreate: serverPageData().canCreate ?? true };
}
