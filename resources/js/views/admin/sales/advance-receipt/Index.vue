<template>
    <PageHeader title="Advance Receipts" subtitle="Manage customer advance payments" @refresh="fetchAdvances">
        <template #actions>
            <button v-can="'create_advance_receipt'" type="button" @click.prevent="createModalOpen = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Add Advance
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar v-model="filter.search" placeholder="Search advance" :is-filtered="isFiltered"
            @search="onSearchInput" @reset="resetFilters">
            <template #filters>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);"
                        class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                        data-bs-toggle="dropdown">
                        {{ selectedStatus || 'Status' }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', '')">All Statuses</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'draft')">Draft</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1"
                                @click="setFilter('status', 'approved')">Approved</a>
                        </li>
                    </ul>
                </div>
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="advanceColumns"
                    :data-source="advances.data" :pagination="false" :loading="advances.loading"
                    @change="handleTableChange">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (advances.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="advances.meta" />
            </div>
        </div>
    </div>

    <!-- Detail panel -->
    <div v-if="viewingAdvance" class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Advance Details — {{ viewingAdvance.advance_no }}</h6>
            <button type="button" class="btn-close" @click="viewingAdvance = null"></button>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-sm-3">
                    <div class="text-muted small">Customer</div>
                    <div class="fw-medium">{{ viewingAdvance.party_name }}</div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">Date</div>
                    <div>{{ viewingAdvance.advance_date }}</div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">Amount</div>
                    <div>{{ formatMoney(viewingAdvance.amount) }}</div>
                </div>
                <div class="col-sm-3">
                    <div class="text-muted small">Balance</div>
                    <div class="fw-bold text-success">{{ formatMoney(viewingAdvance.balance) }}</div>
                </div>
            </div>
            <div v-if="viewingAdvance.adjustments && viewingAdvance.adjustments.length">
                <h6 class="text-muted">Adjustments</h6>
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th class="text-end">Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="adj in viewingAdvance.adjustments" :key="adj.id">
                            <td>{{ adj.invoice_no }}</td>
                            <td class="text-end">{{ formatMoney(adj.amount) }}</td>
                            <td>{{ adj.adjusted_at ? adj.adjusted_at.slice(0, 10) : '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="text-muted small">No adjustments yet.</div>
        </div>
    </div>

    <AdvanceReceiptModal v-model:open="createModalOpen" @saved="fetchAdvances" />
    <AdjustModal v-model:advance="adjustingAdvance" @saved="fetchAdvances" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import AdvanceReceiptModal from './AdvanceReceiptModal.vue';
import AdjustModal from './AdjustModal.vue';
import { useAdvanceReceiptStore } from '@/stores/admin/sales/advance-receipt.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { advanceColumns, createRowActions } from './tableConfig.js';
import { formatMoney } from '@/helpers/formatMoney.js';

const advanceStore = useAdvanceReceiptStore();
const { advances } = storeToRefs(advanceStore);

const createModalOpen = ref(false);
const viewingAdvance = ref(null);
const adjustingAdvance = ref(null);

const fetchAdvances = () => advanceStore.getAdvances({ filter });

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', page: 1, limit: 10 },
    onFilter: fetchAdvances,
});

const selectedStatus = computed(() => {
    const s = filter.status;
    if (!s) return '';
    return { draft: 'Draft', approved: 'Approved' }[s] ?? s;
});

function setFilter(key, value) {
    filter[key] = value;
}

const { handleTableChange } = useTablePagination({
    meta: computed(() => advances.value.meta),
    filter,
});

const { confirmDelete, confirmAction } = useConfirmAction();

const handleView = async (record) => {
    await advanceStore.getAdvance(record.id);
    viewingAdvance.value = advanceStore.advance.data;
};

const handleApprove = (id) => {
    confirmAction(
        'Approve this advance receipt?',
        () => advanceStore.approveAdvance(id),
        fetchAdvances,
    );
};

const handleAdjust = (record) => {
    adjustingAdvance.value = record;
};

const handleVoid = (id) => {
    confirmAction(
        'Void this advance receipt? This will reverse the GL journal.',
        () => advanceStore.voidAdvance(id),
        fetchAdvances,
    );
};

const handleDelete = (id) => {
    confirmDelete(
        () => advanceStore.deleteAdvance(id),
        fetchAdvances,
    );
};

const rowActions = createRowActions({
    onView: handleView,
    onApprove: handleApprove,
    onAdjust: handleAdjust,
    onVoid: handleVoid,
    onDelete: handleDelete,
});
</script>
