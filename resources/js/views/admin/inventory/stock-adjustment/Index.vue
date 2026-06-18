<template>
    <PageHeader title="Stock Adjustments" subtitle="Manage your stock adjustments" @refresh="fetchAdjustments">
        <template #actions>
            <button
                v-can="'create_stock_adjustment'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>Add Adjustment
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search reference"
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters" />

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="stockAdjustmentColumns"
                    :data-source="adjustments.data"
                    :pagination="false"
                    :loading="adjustments.loading">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (adjustments.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge" :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="adjustments.meta" />
            </div>
        </div>
    </div>

    <CreateStockAdjustment v-model:create-modal-opened="createModalOpened" />
    <EditStockAdjustment v-model:adjustment_id="editAdjustmentId" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import CreateStockAdjustment from './Create.vue';
import EditStockAdjustment from './Edit.vue';
import { useStockAdjustmentStore } from '@/stores/admin/inventory/stock-adjustment.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { stockAdjustmentColumns, createRowActions } from './tableConfig.js';

const stockAdjustmentStore = useStockAdjustmentStore();
const { adjustments } = storeToRefs(stockAdjustmentStore);

const createModalOpened = ref(false);
const editAdjustmentId = ref('');

const { confirmAction, confirmDelete } = useConfirmAction();

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', page: 1, limit: 25 },
    onFilter: (f) => stockAdjustmentStore.getAdjustments({ filter: f }),
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => adjustments.value.meta),
    filter,
});

function fetchAdjustments() {
    stockAdjustmentStore.getAdjustments({ filter });
}

const rowActions = createRowActions({
    onEdit: (id) => {
        editAdjustmentId.value = id;
    },
    onApprove: (id) => confirmAction({
        title: 'Approve Stock Adjustment?',
        text: 'This will mark the adjustment as approved.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => stockAdjustmentStore.approveAdjustment(id),
        onSuccess: fetchAdjustments,
    }),
    onDelete: (id) => confirmDelete(
        () => stockAdjustmentStore.deleteAdjustment(id),
        fetchAdjustments,
    ),
});
</script>
