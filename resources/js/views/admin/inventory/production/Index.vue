<template>
    <PageHeader
        title="Production Orders"
        subtitle="Manufacturing & assembly orders"
        @refresh="fetchOrders">
        <template #actions>
            <button
                v-can="'create_production_order'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>New Order
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search order number"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <div class="btn-group btn-group-sm me-2">
                    <button
                        v-for="s in STATUS_TABS"
                        :key="s.value"
                        :class="['btn', filter.status === s.value ? 'btn-primary' : 'btn-outline-secondary']"
                        @click="filter.status = s.value">
                        {{ s.label }}
                    </button>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="productionOrderColumns"
                    :data-source="orders.data"
                    :pagination="false"
                    :loading="orders.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (orders.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'product'">
                            {{ record.bom?.product_variant?.product?.name }}
                        </template>
                        <template v-else-if="column.key === 'qty'">
                            {{ record.produced_qty ?? 0 }} / {{ record.planned_qty }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="STATUS_BADGE[record.status] ?? 'bg-info'">
                                {{ record.status?.replace('_', ' ') }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination
                    v-model:page="filter.page"
                    v-model:limit="filter.limit"
                    :meta="orders.meta"
                />
            </div>
        </div>
    </div>

    <CreateOrder v-model:create-modal-opened="createModalOpened" @saved="fetchOrders" />
    <DetailOrder v-model:order-id="detailOrderId" @completed="fetchOrders" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateOrder from './Create.vue';
import DetailOrder from './Detail.vue';
import { useProductionOrderStore } from '@/stores/admin/inventory/production.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { productionOrderColumns, STATUS_BADGE, createRowActions } from './tableConfig.js';

const STATUS_TABS = [
    { value: '',            label: 'All' },
    { value: 'draft',       label: 'Draft' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed',   label: 'Completed' },
    { value: 'cancelled',   label: 'Cancelled' },
];

const productionStore = useProductionOrderStore();
const { orders } = storeToRefs(productionStore);

const createModalOpened = ref(false);
const detailOrderId = ref(null);

const { confirmAction, confirmDelete } = useConfirmAction();

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 25 },
    onFilter: (f) => productionStore.getOrders({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => orders.value.meta),
    filter,
});

function fetchOrders() {
    productionStore.getOrders({ filter });
}

const rowActions = createRowActions({
    onView: (record) => {
        detailOrderId.value = record.id;
    },
    onStart: (id) => confirmAction({
        title: 'Start Production Order?',
        text: 'This will begin production and reserve materials.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Start',
        action: () => productionStore.startOrder(id),
        onSuccess: fetchOrders,
    }),
    onDetail: (record) => {
        detailOrderId.value = record.id;
    },
    onCancel: (id) => confirmAction({
        title: 'Cancel Production Order?',
        text: 'This will release reserved stock and cancel the order.',
        icon: 'warning',
        confirmButtonColor: 'red',
        confirmButtonText: 'Cancel Order',
        action: () => productionStore.cancelOrder(id),
        onSuccess: fetchOrders,
    }),
});
</script>
