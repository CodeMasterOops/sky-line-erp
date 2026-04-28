import { computed } from 'vue';

/**
 * Reusable table pagination composable for Ant Design Vue <a-table>.
 *
 * Encapsulates the repeated `pagination` computed + `handleTableChange`
 * pattern that exists across every index page.
 *
 * @param {object} options
 * @param {import('vue').ComputedRef|import('vue').Ref} options.meta
 *   A ref/computed pointing to the API response meta object:
 *   { total, current_page, per_page }
 * @param {object} options.filter
 *   The reactive filter object. Must have `page` and `limit` keys.
 * @param {function} [options.onPageChange]
 *   Optional callback fired after page/limit is updated.
 *   Not needed when using useUrlFilter (URL change triggers fetch automatically).
 *   Required for pages that manage their own fetch cycle.
 *
 * @returns {{ pagination: ComputedRef, handleTableChange: function }}
 *
 * Usage WITH useUrlFilter (no onPageChange needed):
 *   const { pagination, handleTableChange } = useTablePagination({
 *     meta: computed(() => orders.value.meta),
 *     filter,
 *   });
 *
 * Usage WITHOUT useUrlFilter:
 *   const { pagination, handleTableChange } = useTablePagination({
 *     meta: computed(() => orders.value.meta),
 *     filter,
 *     onPageChange: fetchOrders,
 *   });
 */
export function useTablePagination({ meta, filter, onPageChange } = {}) {
    const pagination = computed(() => ({
        total:           meta.value?.total           || 0,
        current:         meta.value?.current_page    || 1,
        pageSize:        meta.value?.per_page        || filter.limit,
        showSizeChanger: true,
        showQuickJumper: true,
    }));

    function handleTableChange(paginationEvent) {
        filter.page  = paginationEvent.current;
        filter.limit = paginationEvent.pageSize;
        onPageChange?.();
    }

    return { pagination, handleTableChange };
}
