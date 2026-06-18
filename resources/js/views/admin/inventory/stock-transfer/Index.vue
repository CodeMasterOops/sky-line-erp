<template>
    <PageHeader
        title="Stock Transfers"
        subtitle="Manage your stock transfers"
        @refresh="fetchTransfers">
        <template #actions>
            <button
                v-can="'create_stock_transfer'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Add Transfer
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search reference"
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
                    :columns="stockTransferColumns"
                    :data-source="transfers.data"
                    :pagination="false"
                    :loading="transfers.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (transfers.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="STATUS_BADGE[record.status] ?? 'bg-secondary'">
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
                    :meta="transfers.meta"
                />
            </div>
        </div>
    </div>

    <CreateStockTransfer v-model:create-modal-opened="createModalOpened" />
    <EditStockTransfer v-model:transfer_id="editTransferId" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateStockTransfer from './Create.vue';
import EditStockTransfer from './Edit.vue';
import { useStockTransferStore } from '@/stores/admin/inventory/stock-transfer.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { stockTransferColumns, STATUS_BADGE, STATUS_TABS, createRowActions } from './tableConfig.js';

const stockTransferStore = useStockTransferStore();
const { transfers } = storeToRefs(stockTransferStore);

const createModalOpened = ref(false);
const editTransferId = ref('');

const { confirmAction, confirmDelete } = useConfirmAction();

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 25 },
    onFilter: (f) => stockTransferStore.getTransfers({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => transfers.value.meta),
    filter,
});

function fetchTransfers() {
    stockTransferStore.getTransfers({ filter });
}

const rowActions = createRowActions({
    onEdit: (id) => {
        editTransferId.value = id;
    },
    onDispatch: (id) => confirmAction({
        title: 'Dispatch Stock Transfer?',
        text: 'This will mark the transfer as in-transit.',
        icon: 'question',
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'Dispatch',
        action: () => stockTransferStore.dispatchTransfer(id),
        onSuccess: fetchTransfers,
    }),
    onReceive: (id) => confirmAction({
        title: 'Receive Stock Transfer?',
        text: 'This will approve the transfer and update destination stock.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Receive',
        action: () => stockTransferStore.receiveTransfer(id),
        onSuccess: fetchTransfers,
    }),
    onDelete: (id) => confirmDelete(
        () => stockTransferStore.deleteTransfer(id),
        fetchTransfers,
    ),
});
</script>
