/**
 * Tag every route in a module's route file with `meta.module`, so the router
 * guard can turn a disabled module's pages away without each record repeating
 * the key.
 *
 * A record that already declares `meta.module` keeps it — that is how a
 * sub-module (payroll inside hr, manufacturing inside inventory) opts out of
 * its file's default, matching the inline `module:` route groups on the server.
 *
 * @param {Array<object>} routes
 * @param {string} moduleKey
 * @returns {Array<object>}
 */
export function withModule(routes, moduleKey) {
    if (!Array.isArray(routes)) return [];

    return routes.map((route) => ({
        ...route,
        meta: {
            ...(route.meta ?? {}),
            module: route.meta?.module ?? moduleKey,
        },
        ...(Array.isArray(route.children)
            ? { children: withModule(route.children, route.meta?.module ?? moduleKey) }
            : {}),
    }));
}
