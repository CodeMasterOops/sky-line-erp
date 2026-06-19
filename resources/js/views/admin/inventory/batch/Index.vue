<template>
    <PageHeader title="Batch / Lot Tracking" subtitle="FEFO inventory — expiry management" @refresh="fetchBatches">
        <template #actions>
            <button type="button" class="btn btn-warning me-2" @click="showExpiryAlerts">
                <i class="ti ti-alert-triangle me-1"></i> Expiry Alerts
            </button>
            <button v-can="'create_batch'" type="button" class="btn btn-primary d-flex align-items-center" @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i> Add Batch
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <VTableToolbar
            v-model="filter.search"
            placeholder="Search batch no, lot no..."
            :is-filtered="isFiltered"
            @search="onSearchInput"
            @reset="resetFilters">
            <template #filters>
                <select class="form-select form-select-sm" v-model="filter.status">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="quarantine">Quarantine</option>
                    <option value="expired">Expired</option>
                    <option value="depleted">Depleted</option>
                    <option value="recalled">Recalled</option>
                </select>
                <input
                    type="number"
                    class="form-control form-control-sm"
                    v-model="filter.expiring_days"
                    placeholder="Expiring in (days)"
                    style="width: 160px;"
                />
            </template>
        </VTableToolbar>

        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="columns"
                    :data-source="batches.data"
                    :loading="batches.loading"
                    :pagination="false">
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ (batches.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                        </template>
                        <template v-else-if="column.key === 'product'">
                            {{ record.product_variant?.product?.name }}
                        </template>
                        <template v-else-if="column.key === 'expiry_date'">
                            <span :class="expiryClass(record.expiry_date)">
                                {{ record.expiry_date ?? '—' }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span :class="statusBadge(record.status)" class="badge text-capitalize">
                                {{ record.status_label ?? record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'remaining_qty'">
                            {{ record.remaining_qty }} / {{ record.initial_qty }}
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
                <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="batches.meta" />
            </div>
        </div>
    </div>

    <VModal :show-modal="alertModal" @close-click="alertModal = false" title="Expiry Alerts">
        <template #modal-body>
            <p class="text-muted small mb-3">Batches expiring within the next {{ alertDays }} days.</p>
            <div v-if="alerts.length === 0" class="text-muted text-center py-3">No batches expiring soon.</div>
            <table v-else class="table table-sm">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th>Remaining Qty</th>
                        <th>Warehouse</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in alerts" :key="b.id">
                        <td>{{ b.product_variant?.product?.name }}</td>
                        <td>{{ b.batch_no }}</td>
                        <td class="text-danger fw-bold">{{ b.expiry_date }}</td>
                        <td>{{ b.remaining_qty }}</td>
                        <td>{{ b.warehouse?.name }}</td>
                    </tr>
                </tbody>
            </table>
        </template>
    </VModal>

    <CreateBatch v-model:create-modal-opened="createModalOpened" />
    <EditBatch v-model:batch_id="editBatchId" />
</template>

<script setup>
import { ref } from 'vue';
import { storeToRefs } from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useBatchStore } from '@/stores/admin/inventory/batch.js';
import CreateBatch from './Create.vue';
import EditBatch from './Edit.vue';

const batchStore = useBatchStore();
const { batches } = storeToRefs(batchStore);

const createModalOpened = ref(false);
const editBatchId = ref('');
const alertModal = ref(false);
const alerts = ref([]);
const alertDays = ref(30);

const { filter, onSearchInput, resetFilters, isFiltered } = useUrlFilter({
    defaults: { search: '', status: '', expiring_days: '', page: 1, limit: 25 },
    onFilter: (f) => batchStore.getBatches({ filter: f }),
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Product', key: 'product' },
    { title: 'Batch No', dataIndex: 'batch_no' },
    { title: 'Lot No', dataIndex: 'lot_no' },
    { title: 'Warehouse', dataIndex: ['warehouse', 'name'] },
    { title: 'Mfg Date', dataIndex: 'mfg_date' },
    { title: 'Expiry Date', key: 'expiry_date' },
    { title: 'Qty (Rem/Init)', key: 'remaining_qty' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const rowActions = [
    {
        key: 'quarantine',
        icon: 'ti-shield-lock',
        title: 'Put on quarantine hold',
        class: 'text-warning',
        condition: (record) => record.status === 'active',
        handler: (record) => updateStatus(record, 'quarantine'),
    },
    {
        key: 'release',
        icon: 'ti-shield-check',
        title: 'Release hold (set active)',
        class: 'text-success',
        condition: (record) => record.status === 'quarantine',
        handler: (record) => updateStatus(record, 'active'),
    },
    {
        key: 'recall',
        icon: 'ti-alert-octagon',
        title: 'Recall batch',
        class: 'text-danger',
        condition: (record) => ['active', 'quarantine'].includes(record.status),
        handler: (record) => updateStatus(record, 'recalled'),
    },
    {
        key: 'edit',
        icon: 'ti-edit',
        title: 'Edit',
        class: 'edit-icon',
        handler: (record) => { editBatchId.value = record.id; },
    },
];

async function updateStatus(record, status) {
    try {
        const res = await batchStore.updateBatch(record.id, { status });
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    }
}

function fetchBatches() {
    batchStore.getBatches({ filter });
}

async function showExpiryAlerts() {
    const { data } = await apiAdmin(`batch/expiry-alerts?days=${alertDays.value}`);
    alerts.value = data.data;
    alertModal.value = true;
}

function expiryClass(date) {
    if (!date) { return ''; }
    const diff = Math.ceil((new Date(date) - new Date()) / 86400000);
    if (diff < 0) { return 'text-danger fw-bold'; }
    if (diff <= 30) { return 'text-warning fw-bold'; }
    return '';
}

function statusBadge(status) {
    return {
        active: 'bg-success',
        quarantine: 'bg-warning',
        expired: 'bg-danger',
        depleted: 'bg-secondary',
        recalled: 'bg-dark',
    }[status] ?? 'bg-info';
}
</script>
