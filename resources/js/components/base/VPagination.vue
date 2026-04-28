<template>
    <div
        v-if="meta && meta.total > 0"
        class="d-flex align-items-center justify-content-between flex-wrap gap-3 py-3 px-1">

        <!-- Left: entries summary -->
        <div class="text-muted small">
            Showing
            <strong>{{ meta.from ?? 1 }}</strong>
            to
            <strong>{{ meta.to ?? meta.total }}</strong>
            of
            <strong>{{ meta.total }}</strong>
            entries
        </div>

        <!-- Right: per-page + page buttons -->
        <div class="d-flex align-items-center gap-3 flex-wrap">

            <!-- Per page selector -->
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted small mb-0 text-nowrap">Rows per page</label>
                <select
                    class="form-select form-select-sm"
                    style="width: auto;"
                    :value="limit"
                    @change="onLimitChange">
                    <option v-for="n in pageSizes" :key="n" :value="n">{{ n }}</option>
                </select>
            </div>

            <!-- Page buttons -->
            <ul class="pagination pagination-sm mb-0">
                <!-- Previous -->
                <li class="page-item" :class="{ disabled: page <= 1 }">
                    <button
                        type="button"
                        class="page-link"
                        :disabled="page <= 1"
                        @click="go(page - 1)">
                        <i class="ti ti-chevron-left"></i>
                    </button>
                </li>

                <!-- Page range with ellipsis -->
                <template v-for="(p, i) in pageRange" :key="i">
                    <li v-if="p === ELLIPSIS" class="page-item disabled">
                        <span class="page-link">…</span>
                    </li>
                    <li v-else class="page-item" :class="{ active: p === page }">
                        <button type="button" class="page-link" @click="go(p)">{{ p }}</button>
                    </li>
                </template>

                <!-- Next -->
                <li class="page-item" :class="{ disabled: page >= totalPages }">
                    <button
                        type="button"
                        class="page-link"
                        :disabled="page >= totalPages"
                        @click="go(page + 1)">
                        <i class="ti ti-chevron-right"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const ELLIPSIS = '…';

const props = defineProps({
    meta: {
        type: Object,
        required: true,
    },
    page: {
        type: Number,
        default: 1,
    },
    limit: {
        type: Number,
        default: 10,
    },
    pageSizes: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
});

const emit = defineEmits(['update:page', 'update:limit']);

const totalPages = computed(() =>
    props.meta?.last_page
        ?? Math.ceil((props.meta?.total || 0) / (props.meta?.per_page || props.limit))
        ?? 1
);

/**
 * Builds a smart page range with ellipsis for large page counts.
 * Always shows first, last, current ± 1, with ellipsis in gaps.
 * e.g. [1, '…', 4, 5, 6, '…', 20]
 */
const pageRange = computed(() => {
    const total = totalPages.value;
    const current = props.page;

    if (total <= 7) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const pages = new Set([1, total, current, current - 1, current + 1].filter(p => p >= 1 && p <= total));
    const sorted = [...pages].sort((a, b) => a - b);

    const result = [];
    for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
            result.push(ELLIPSIS);
        }
        result.push(sorted[i]);
    }
    return result;
});

function go(p) {
    if (p < 1 || p > totalPages.value || p === props.page) return;
    emit('update:page', p);
}

function onLimitChange(e) {
    emit('update:limit', Number(e.target.value));
    emit('update:page', 1); // reset to first page on limit change
}
</script>
