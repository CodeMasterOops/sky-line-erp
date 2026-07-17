<template>
    <div class="osl">
        <div v-if="items.length" class="osl__toolbar">
            <div class="osl__counts">
                <span class="badge bg-light text-dark border">
                    {{ items.length }} loaded
                </span>
                <span class="badge bg-primary-subtle text-primary">
                    {{ filledCount }} with qty
                </span>
            </div>
            <div class="osl__controls">
                <div class="osl__search">
                    <i class="ti ti-search osl__search-icon"></i>
                    <input
                        type="text"
                        class="form-control form-control-sm"
                        v-model="search"
                        placeholder="Filter products…"
                    />
                </div>
                <div class="form-check form-switch mb-0 text-nowrap">
                    <input
                        id="osl-filled-only"
                        type="checkbox"
                        class="form-check-input"
                        v-model="filledOnly"
                    />
                    <label for="osl-filled-only" class="form-check-label small">
                        With qty only
                    </label>
                </div>
            </div>
        </div>

        <div class="table-responsive no-pagination">
            <table class="table datanew table-bordered mb-0 opening-stock-lines-table">
                <thead>
                <tr>
                    <th class="ose-col-sn">SN</th>
                    <th class="ose-col-product">Product</th>
                    <th class="ose-col-qty">
                        Qty
                        <VRequiredMark />
                    </th>
                    <th class="ose-col-cost">
                        Unit cost
                        <VRequiredMark v-if="costRequired" />
                    </th>
                    <th class="ose-col-batch">Batch</th>
                    <th class="text-center ose-col-action">Action</th>
                </tr>
                </thead>
                <tbody>
                <tr v-if="!items.length">
                    <td colspan="6" class="text-center text-muted py-4">
                        Search and select a product to add lines.
                    </td>
                </tr>
                <tr v-else-if="!filteredItems.length">
                    <td colspan="6" class="text-center text-muted py-4">
                        No products match your filter.
                    </td>
                </tr>
                <tr
                    v-for="(row, i) in pagedItems"
                    :key="`${row.index}-${row.item.product_variant_id}`">
                    <td>{{ (page - 1) * limit + i + 1 }}</td>
                    <td class="text-start text-truncate ose-col-product" :title="row.item.product_label">
                        {{ row.item.product_label }}
                    </td>
                    <td class="ose-col-qty ose-cell-tight">
                        <VInput
                            input-type="number"
                            input-class="form-control form-control-sm"
                            v-model="items[row.index].quantity"
                            @validate="emit('validate', `items[${row.index}].quantity`)"
                            :error="errors[`items[${row.index}].quantity`]"
                        />
                    </td>
                    <td class="ose-col-cost ose-cell-tight">
                        <VInput
                            input-type="number"
                            input-class="form-control form-control-sm"
                            v-model="items[row.index].unit_cost"
                            @validate="emit('validate', `items[${row.index}].unit_cost`)"
                            :error="errors[`items[${row.index}].unit_cost`]"
                        />
                    </td>
                    <td class="ose-col-batch">
                        <slot
                            v-if="row.item.is_batch_tracked"
                            name="batch"
                            :item="items[row.index]"
                            :index="row.index"
                        />
                    </td>
                    <td class="text-center ose-col-action">
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            @click="emit('remove', row.index)">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <VPagination
            v-if="filteredItems.length"
            v-model:page="page"
            v-model:limit="limit"
            :meta="meta"
        />
    </div>
</template>

<script setup>
import {computed, ref, watch} from 'vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';
import VPagination from '@/components/base/VPagination.vue';

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    costRequired: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['validate', 'remove']);

const search = ref('');
const filledOnly = ref(false);
const page = ref(1);
const limit = ref(25);

const hasQuantity = (item) => {
    const n = parseFloat(String(item.quantity ?? ''));
    return Number.isFinite(n) && n > 0;
};

const filledCount = computed(() => props.items.filter(hasQuantity).length);

const decoratedItems = computed(() =>
    props.items.map((item, index) => ({item, index}))
);

const filteredItems = computed(() => {
    const term = search.value.trim().toLowerCase();
    return decoratedItems.value.filter(({item}) => {
        if (filledOnly.value && !hasQuantity(item)) {
            return false;
        }
        if (term && !String(item.product_label ?? '').toLowerCase().includes(term)) {
            return false;
        }
        return true;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredItems.value.length / limit.value))
);

const pagedItems = computed(() => {
    const start = (page.value - 1) * limit.value;
    return filteredItems.value.slice(start, start + limit.value);
});

const meta = computed(() => {
    const total = filteredItems.value.length;
    const from = total === 0 ? 0 : (page.value - 1) * limit.value + 1;
    const to = Math.min(page.value * limit.value, total);
    return {
        total,
        per_page: limit.value,
        current_page: page.value,
        last_page: totalPages.value,
        from,
        to,
    };
});

watch([filteredItems, totalPages], () => {
    if (page.value > totalPages.value) {
        page.value = totalPages.value;
    }
});

watch([search, filledOnly], () => {
    page.value = 1;
});
</script>

<style scoped>
.osl__toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.osl__counts {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.osl__controls {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.osl__search {
    position: relative;
}

.osl__search .form-control {
    padding-left: 2rem;
    width: 16rem;
    max-width: 100%;
}

.osl__search-icon {
    position: absolute;
    top: 50%;
    left: 0.6rem;
    transform: translateY(-50%);
    color: var(--bs-secondary-color, #6c757d);
    pointer-events: none;
}

.opening-stock-lines-table {
    table-layout: fixed;
    width: 100%;
}

.opening-stock-lines-table th.ose-col-sn,
.opening-stock-lines-table td:first-child {
    width: 2.75rem;
}

.opening-stock-lines-table th.ose-col-product {
    width: 45%;
}

.opening-stock-lines-table th.ose-col-qty,
.opening-stock-lines-table td.ose-col-qty {
    width: 5rem;
}

.opening-stock-lines-table th.ose-col-cost,
.opening-stock-lines-table td.ose-col-cost {
    width: 6.25rem;
}

.opening-stock-lines-table th.ose-col-action {
    width: 3rem;
}
</style>
