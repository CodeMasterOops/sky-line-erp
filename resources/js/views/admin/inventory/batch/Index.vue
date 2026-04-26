<template>
    <PageHeader title="Batch / Lot Tracking" subtitle="FEFO inventory — expiry management" @refresh="fetchBatches(true)">
        <template #actions>
            <button type="button" class="btn btn-warning me-2" @click="showExpiryAlerts">
                <i class="ti ti-alert-triangle me-1"></i> Expiry Alerts
            </button>
            <button v-can="'create_batch'" type="button" class="btn btn-primary" @click="showForm = true">
                <i class="ti ti-circle-plus me-2"></i> Add Batch
            </button>
        </template>
    </PageHeader>

    <!-- Filters -->
    <section class="section">
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select class="form-select form-select-sm" v-model="filter.status" @change="fetchBatches(true)">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="expired">Expired</option>
                            <option value="depleted">Depleted</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Expiring within (days)</label>
                        <input type="number" class="form-control form-control-sm" v-model="filter.expiring_days"
                               placeholder="e.g. 30" @change="fetchBatches(true)" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-secondary w-100" @click="clearFilters">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="batches" :loading="loading" :pagination="pagination"
                             @change="handleTableChange" row-key="id">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'product'">
                                {{ record.product_variant?.product?.name }}
                            </template>
                            <template v-if="column.key === 'expiry_date'">
                                <span :class="expiryClass(record.expiry_date)">
                                    {{ record.expiry_date ?? '—' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="statusBadge(record.status)" class="badge">
                                    {{ record.status }}
                                </span>
                            </template>
                            <template v-if="column.key === 'remaining_qty'">
                                {{ record.remaining_qty }} / {{ record.initial_qty }}
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>

    <!-- Add Batch Modal -->
    <div v-if="showForm" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Record New Batch</h5>
                    <button type="button" class="btn-close" @click="showForm = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Variant <span class="text-danger">*</span></label>
                            <input v-model="form.product_variant_id" type="number" class="form-control" placeholder="Variant ID" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select v-model="form.warehouse_id" class="form-select">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Batch No <span class="text-danger">*</span></label>
                            <input v-model="form.batch_no" class="form-control" placeholder="B-001" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lot No</label>
                            <input v-model="form.lot_no" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initial Qty <span class="text-danger">*</span></label>
                            <input v-model="form.initial_qty" type="number" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mfg Date</label>
                            <input v-model="form.mfg_date" type="date" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date</label>
                            <input v-model="form.expiry_date" type="date" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit Cost</label>
                            <input v-model="form.unit_cost" type="number" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea v-model="form.remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showForm = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveBatch">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save Batch
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiry Alerts Modal -->
    <div v-if="alertModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="ti ti-alert-triangle me-2"></i>Expiry Alerts (next {{ alertDays }} days)</h5>
                    <button type="button" class="btn-close" @click="alertModal = false"></button>
                </div>
                <div class="modal-body">
                    <div v-if="alerts.length === 0" class="text-muted text-center py-3">No batches expiring soon.</div>
                    <table v-else class="table table-sm">
                        <thead><tr><th>Product</th><th>Batch</th><th>Expiry</th><th>Remaining Qty</th><th>Warehouse</th></tr></thead>
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
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const batches = ref([]);
const warehouses = ref([]);
const showForm = ref(false);
const alertModal = ref(false);
const alerts = ref([]);
const alertDays = ref(30);
const pagination = ref({ current: 1, pageSize: 25, total: 0 });

const filter = ref({ status: '', expiring_days: '' });

const form = ref({
    product_variant_id: '', warehouse_id: '', batch_no: '', lot_no: '',
    initial_qty: '', mfg_date: '', expiry_date: '', unit_cost: '', remarks: '',
});

const columns = [
    { title: 'Product', key: 'product', dataIndex: 'product_variant' },
    { title: 'Batch No', key: 'batch_no', dataIndex: 'batch_no' },
    { title: 'Lot No', key: 'lot_no', dataIndex: 'lot_no' },
    { title: 'Warehouse', key: 'warehouse', dataIndex: ['warehouse', 'name'] },
    { title: 'Mfg Date', key: 'mfg_date', dataIndex: 'mfg_date' },
    { title: 'Expiry Date', key: 'expiry_date', dataIndex: 'expiry_date' },
    { title: 'Qty (Rem/Init)', key: 'remaining_qty' },
    { title: 'Status', key: 'status', dataIndex: 'status' },
];

onMounted(() => {
    fetchBatches();
    fetchWarehouses();
});

async function fetchBatches(reset = false) {
    if (reset) pagination.value.current = 1;
    loading.value = true;
    try {
        const params = new URLSearchParams({
            page: pagination.value.current,
            per_page: pagination.value.pageSize,
            ...(filter.value.status && { status: filter.value.status }),
            ...(filter.value.expiring_days && { expiring_days: filter.value.expiring_days }),
        });
        const { data } = await apiAdmin(`batch?${params}`);
        batches.value = data.data;
        pagination.value.total = data.total;
    } finally {
        loading.value = false;
    }
}

async function fetchWarehouses() {
    const { data } = await apiAdmin('warehouse');
    warehouses.value = data.data;
}

async function showExpiryAlerts() {
    const { data } = await apiAdmin(`batch/expiry-alerts?days=${alertDays.value}`);
    alerts.value = data.data;
    alertModal.value = true;
}

async function saveBatch() {
    saving.value = true;
    try {
        await apiAdmin('batch', 'post', form.value);
        toast('Batch saved successfully');
        showForm.value = false;
        fetchBatches(true);
        resetForm();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
}

function resetForm() {
    form.value = { product_variant_id: '', warehouse_id: '', batch_no: '', lot_no: '',
        initial_qty: '', mfg_date: '', expiry_date: '', unit_cost: '', remarks: '' };
}

function clearFilters() {
    filter.value = { status: '', expiring_days: '' };
    fetchBatches(true);
}

function handleTableChange(pag) {
    pagination.value.current = pag.current;
    fetchBatches();
}

function expiryClass(date) {
    if (!date) return '';
    const diff = Math.ceil((new Date(date) - new Date()) / 86400000);
    if (diff < 0) return 'text-danger fw-bold';
    if (diff <= 30) return 'text-warning fw-bold';
    return '';
}

function statusBadge(status) {
    return { active: 'bg-success', expired: 'bg-danger', depleted: 'bg-secondary' }[status] ?? 'bg-info';
}
</script>
