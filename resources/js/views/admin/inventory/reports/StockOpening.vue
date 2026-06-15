<template>
    <PageHeader hide-action-buttons title="Stock Opening Report" subtitle="History of opening stock entries with item details" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-file-description fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Entries</p>
                            <h4 class="mb-0">{{ kpi.total_entries }}</h4>
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
                            <p class="fw-medium mb-1">Total Quantity</p>
                            <h4 class="mb-0">{{ formatMoneyPlain(kpi.total_quantity) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Value</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_value) }}</h4>
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
                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>
                        <select class="form-select" v-model="filters.warehouse_id">
                            <option value="">All Warehouses</option>
                            <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
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
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Warehouse</th>
                                <th>Status</th>
                                <th class="text-end">Items</th>
                                <th class="text-end">Total Qty</th>
                                <th class="text-end">Total Value</th>
                                <th>Remarks</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="9" class="text-center text-muted py-4">Set filters and click Generate to load data.</td>
                            </tr>
                            <template v-for="(row, idx) in rows" :key="idx">
                                <tr>
                                    <td class="text-nowrap">{{ row.date }}</td>
                                    <td class="fw-semibold">{{ row.reference_no }}</td>
                                    <td>{{ row.warehouse }}</td>
                                    <td>
                                        <span class="badge" :class="row.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">{{ row.status }}</span>
                                    </td>
                                    <td class="text-end">{{ row.item_count }}</td>
                                    <td class="text-end">{{ formatMoneyPlain(row.total_quantity) }}</td>
                                    <td class="text-end fw-semibold">{{ formatMoney(row.total_value) }}</td>
                                    <td class="text-muted small">{{ row.remarks }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary py-0" @click="toggleExpand(idx)">
                                            <i class="ti" :class="expanded[idx] ? 'ti-chevron-up' : 'ti-chevron-down'"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded[idx]" class="table-light">
                                    <td colspan="9" class="py-2 px-4">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr class="text-muted">
                                                    <th>Product</th>
                                                    <th>Code</th>
                                                    <th>SKU</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Unit Cost</th>
                                                    <th class="text-end">Total Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(item, i) in row.items" :key="i">
                                                    <td>{{ item.product_name }}</td>
                                                    <td>{{ item.product_code }}</td>
                                                    <td>{{ item.sku }}</td>
                                                    <td class="text-end">{{ formatMoneyPlain(item.quantity) }}</td>
                                                    <td class="text-end">{{ formatMoney(item.unit_cost) }}</td>
                                                    <td class="text-end fw-semibold">{{ formatMoney(item.total_value) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const warehouseOptions = ref([]);
const expanded = ref({});

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({
    from_date: '',
    to_date: '',
    warehouse_id: '',
});

const kpi = computed(() => summary.value ?? {total_entries: 0, total_quantity: 0, total_value: 0});

const toggleExpand = (idx) => { expanded.value[idx] = !expanded.value[idx]; };

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Reference', 'Warehouse', 'Status', 'Items', 'Total Qty', 'Total Value', 'Remarks'];
    const csvRows = rows.value.map(r => [
        r.date, r.reference_no, r.warehouse, r.status, r.item_count, r.total_quantity, r.total_value, r.remarks,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'stock-opening.csv';
    a.click();
};

const loadReport = async () => {
    loading.value = true;
    expanded.value = {};
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (params[k] === '' || params[k] === null) { delete params[k]; } });
        const res = await apiAdmin('inventory-report/stock-opening', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        warehouseOptions.value = data.warehouse_options || warehouseOptions.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10);
        filters.value.to_date = now.toISOString().slice(0, 10);
    }
    await loadReport();
});
</script>
