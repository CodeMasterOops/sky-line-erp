import { reactive, watch, nextTick, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import debounce from 'lodash/debounce';

function inferType(value) {
    if (typeof value === 'number') return 'number';
    return 'string';
}

function coerce(value, type) {
    if (value === undefined || value === null) return undefined;
    if (type === 'number') {
        const n = Number(value);
        return isNaN(n) ? undefined : n;
    }
    return String(value);
}

function parseFromQuery(query, defaults) {
    const result = {};
    for (const key of Object.keys(defaults)) {
        const raw = Array.isArray(query[key]) ? query[key][0] : query[key];
        const type = inferType(defaults[key]);
        const coerced = coerce(raw, type);
        result[key] = coerced !== undefined ? coerced : defaults[key];
    }
    return result;
}

function buildQuery(filter, defaults) {
    const query = {};
    for (const key of Object.keys(defaults)) {
        const value = filter[key];
        if (value !== defaults[key] && value !== '' && value !== null && value !== undefined) {
            query[key] = String(value);
        }
    }
    return query;
}

function queriesEqual(a, b) {
    const keysA = Object.keys(a);
    const keysB = Object.keys(b);
    if (keysA.length !== keysB.length) return false;
    return keysA.every(k => a[k] === b[k]);
}

/**
 * Reusable URL-based filter composable.
 *
 * Flow for user-initiated changes:
 *   filter changes → pushToUrl() → onFilter() called + router.push()
 *   route.query watcher fires but _pushing=true → skips (no double fetch)
 *
 * Flow for browser back/forward:
 *   route.query changes externally (_pushing=false) → sync filter → onFilter()
 *
 * _pendingQuery prevents double pushes when debouncedSearchPush sets
 * filter.page (which triggers the nonSearchKeys watcher) and then also
 * calls pushToUrl() itself — the second call sees the pending query and skips.
 */
export function useUrlFilter({ defaults = {}, onFilter } = {}) {
    const route  = useRoute();
    const router = useRouter();

    const filter = reactive(parseFromQuery(route.query, defaults));

    // Guards against the route.query watcher double-fetching our own pushes.
    let _pushing     = false;
    // Tracks the query object we are currently navigating to, preventing
    // duplicate pushes from concurrent watcher + explicit pushToUrl() calls.
    let _pendingQuery = null;

    // ─── Core ─────────────────────────────────────────────────────────────────

    function pushToUrl() {
        const query = buildQuery(filter, defaults);

        // Already at this URL — nothing to do.
        if (queriesEqual(route.query, query)) return;

        // Already pushing this exact query — skip duplicate (e.g. when
        // debouncedSearchPush sets filter.page and then calls pushToUrl itself).
        if (_pendingQuery && queriesEqual(_pendingQuery, query)) return;

        _pendingQuery = query;
        _pushing      = true;

        // Fetch before the navigation resolves so data loads immediately.
        onFilter?.(filter);

        router.push({ query }).finally(() => {
            nextTick(() => {
                _pushing      = false;
                _pendingQuery = null;
            });
        });
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    const debouncedSearchPush = debounce(() => {
        filter.page = defaults.page ?? 1;
        pushToUrl();
    }, 300);

    function onSearchInput() {
        debouncedSearchPush();
    }

    // ─── Non-search keys: push immediately on any change ─────────────────────
    // Covers: page, limit, status, type, date_from, date_to, etc.

    const nonSearchKeys = Object.keys(defaults).filter(k => k !== 'search');

    watch(
        nonSearchKeys.map(k => () => filter[k]),
        () => { pushToUrl(); },
        { flush: 'post' }
    );

    // ─── Browser back/forward ─────────────────────────────────────────────────

    watch(
        () => route.query,
        (query) => {
            // Ignore changes triggered by our own router.push.
            if (_pushing) return;

            const parsed = parseFromQuery(query, defaults);
            for (const key of Object.keys(defaults)) {
                if (filter[key] !== parsed[key]) {
                    filter[key] = parsed[key];
                }
            }
            onFilter?.(filter);
        },
        { deep: true }
    );

    // ─── Initial fetch ────────────────────────────────────────────────────────

    onMounted(() => {
        onFilter?.(filter);
    });

    // ─── Reset ────────────────────────────────────────────────────────────────

    function resetFilters() {
        // Cancel any pending search debounce so it doesn't re-apply after reset.
        debouncedSearchPush.cancel();
        Object.assign(filter, { ...defaults });
        // Explicitly push because search key is not in the nonSearchKeys watcher.
        pushToUrl();
    }

    return { filter, onSearchInput, resetFilters };
}
