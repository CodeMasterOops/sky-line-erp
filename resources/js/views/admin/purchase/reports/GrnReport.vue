<template>
    <PageHeader hide-action-buttons title="GRN Report" subtitle="Goods received notes with item quantities and billing status" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-truck-delivery fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total GRNs</p>
                            <h4 class="mb-0">{{ kpi.total_grns }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-stack fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Received Qty</p>
                            <h4 class="mb-0">{{ kpi.total_qty }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-file-check fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Billed Qty</p>
                            <h4 class="mb-0">{{ kpi.billed_qty }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <VDatepicker id="from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Warehouse</label>
                        <select class="form-select" v-model="filters.warehouse_id">
                            <option value="">All Warehouses</option>
                            <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Billing Status</label>
                        <select class="form-select" v-model="filters.billing_status">
                            <option value="">All</option>
                            <option value="billed">Billed</option>
                            <option value="partial">Partially Billed</option>
                            <option value="unbilled">Unbilled</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport" :disabled="loading">
                            {{ loading ? 'Generating...' : 'Generate' }}
                        </button>
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!rows.length" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>GRN #</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Warehouse</th>
                                <th class="text-end">Items</th>
                                <th class="text-end">Received Qty</th>
                                <th class="text-end">Billed Qty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="9" class="text-center text-muted py-4">No GRNs found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td>{{ i + 1 }}</td>
                                <td>{{ row.grn_no }}</td>
                                <td>{{ row.received_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.warehouse_name }}</td>
                                <td class="text-end">{{ row.item_count }}</td>
                                <td class="text-end">{{ row.total_received_qty }}</td>
                                <td class="text-end">{{ row.total_billed_qty }}</td>
                                <td>
                                    <span class="badge" :class="billingStatusClass(row.billing_status)">
                                        {{ row.billing_status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="table-light fw-semibold">
                            <tr>
                                <td colspan="5">Total</td>
                                <td class="text-end">—</td>
                                <td class="text-end">{{ kpi.total_qty }}</td>
                                <td class="text-end">{{ kpi.billed_qty }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const reportData = ref(null);
const loading = ref(false);
const warehouseOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({from_date: '', to_date: '', warehouse_id: '', billing_status: ''});

const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => reportData.value?.summary ?? {total_grns: 0, total_qty: 0, billed_qty: 0});

const billingStatusClass = (status) => {
    const map = {billed: 'bg-success-subtle text-success', partial: 'bg-warning-subtle text-warning', unbilled: 'bg-danger-subtle text-danger'};
    return map[status] ?? 'bg-secondary-subtle text-secondary';
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/grn-report', 'get', params);
        reportData.value = res.data.data;
        warehouseOptions.value = reportData.value?.warehouse_options ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['GRN #', 'Date', 'Supplier', 'Warehouse', 'Items', 'Received Qty', 'Billed Qty', 'Status'];
    const data = rows.value.map(r => [r.grn_no, r.received_date, r.party_name, r.warehouse_name, r.item_count, r.total_received_qty, r.total_billed_qty, r.billing_status]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'grn-report.csv';
    a.click();
};

onMounted(async () => {
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        filters.value.to_date = now.toISOString().slice(0, 10);
    }
    await loadReport();
});
</script>
