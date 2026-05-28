import { onMounted, reactive, watch } from 'vue';

/**
 * Standard server-side pagination wiring for index pages.
 *
 * @param {object} options
 * @param {function} options.fetchFn Receives `{ filter }` and loads data.
 * @param {object} [options.defaults] Default filter values including page/limit.
 * @param {boolean} [options.immediate=true] Fetch on mount.
 */
export function usePaginatedList({ fetchFn, defaults = { page: 1, limit: 25 }, immediate = true } = {}) {
    const filter = reactive({ ...defaults });

    const fetch = () => fetchFn({ filter });

    if (immediate) {
        onMounted(fetch);
    }

    watch(
        () => [filter.page, filter.limit],
        () => {
            fetch();
        },
    );

    function resetPageAndFetch() {
        const onFirstPage = filter.page === 1;
        filter.page = 1;
        if (onFirstPage) {
            fetch();
        }
    }

    return { filter, fetch, resetPageAndFetch };
}
