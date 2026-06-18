<template>
    <PageHeader hide-action-buttons title="Production Variance Report"
        subtitle="Planned vs actual costs for completed production orders" />

    <section class="section">
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <VDatepicker id="pv_from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="pv_to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>
                        <VMultiselect
                            id="pv_warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseOptions"
                            placeholder="All Warehouses"
                        />
                    </div>
                    <div class="col-md-3 d-flex gap-2 align-items-end">
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

        <!-- Summary KPIs -->
        <div v-if="summary" class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-primary-subtle text-primary"><i class="ti ti-clipboard-list fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Orders</p>
                            <h4 class="mb-0">{{ summary.total_orders }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info"><i class="ti ti-currency-rupee fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Standard Cost</p>
                            <h4 class="mb-0">{{ formatMoney(summary.total_standard_cost) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning"><i class="ti ti-cash fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Actual Cost</p>
                            <h4 class="mb-0">{{ formatMoney(summary.total_actual_cost) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span :class="summary.total_variance >= 0 ? 'sale-icon bg-danger-subtle text-danger' : 'sale-icon bg-success-subtle text-success'">
                            <i :class="summary.total_variance >= 0 ? 'ti ti-trending-up fs-24' : 'ti ti-trending-down fs-24'"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Variance</p>
                            <h4 class="mb-0" :class="summary.total_variance >= 0 ? 'text-danger' : 'text-success'">
                                {{ formatMoney(summary.total_variance) }}
                            </h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Order No</th>
                                <th>Product</th>
                                <th>Warehouse</th>
                                <th>Completed</th>
                                <th class="text-end">Planned Qty</th>
                                <th class="text-end">Produced Qty</th>
                                <th class="text-end">Std Cost</th>
                                <th class="text-end">Actual Cost</th>
                                <th class="text-end">Variance</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="10" class="text-center text-muted py-4">
                                    Set filters and click Generate to load data.
                                </td>
                            </tr>
                            <tr v-if="loading">
                                <td colspan="10" class="text-center py-4">
                                    <div class="spinner-border text-primary spinner-border-sm"></div>
                                </td>
                            </tr>
                            <template v-for="row in rows" :key="row.id">
                                <tr @click="toggleExpand(row.id)" style="cursor:pointer">
                                    <td class="fw-medium text-nowrap">{{ row.order_no }}</td>
                                    <td>
                                        {{ row.finished_product }}
                                        <small v-if="row.finished_sku" class="text-muted d-block">{{ row.finished_sku }}</small>
                                    </td>
                                    <td>{{ row.warehouse }}</td>
                                    <td class="text-nowrap">{{ row.completed_at }}</td>
                                    <td class="text-end">{{ row.planned_qty }}</td>
                                    <td class="text-end">{{ row.produced_qty }}</td>
                                    <td class="text-end">{{ formatMoney(row.total_standard_cost) }}</td>
                                    <td class="text-end">{{ formatMoney(row.total_actual_cost) }}</td>
                                    <td class="text-end fw-semibold"
                                        :class="row.total_variance > 0 ? 'text-danger' : row.total_variance < 0 ? 'text-success' : ''">
                                        {{ formatMoney(row.total_variance) }}
                                    </td>
                                    <td class="text-center">
                                        <i :class="expandedRows.has(row.id) ? 'ti ti-chevron-up' : 'ti ti-chevron-down'"
                                            class="text-muted"></i>
                                    </td>
                                </tr>
                                <tr v-if="expandedRows.has(row.id) && row.components?.length">
                                    <td colspan="10" class="p-0 bg-light">
                                        <table class="table table-sm mb-0 border-0">
                                            <thead>
                                                <tr class="bg-light">
                                                    <th class="ps-4">Material</th>
                                                    <th>SKU</th>
                                                    <th class="text-end">Required Qty</th>
                                                    <th class="text-end">Consumed Qty</th>
                                                    <th class="text-end">Unit Cost</th>
                                                    <th class="text-end">Std Cost</th>
                                                    <th class="text-end">Actual Cost</th>
                                                    <th class="text-end">Variance</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="c in row.components" :key="c.material_sku">
                                                    <td class="ps-4">{{ c.material_name }}</td>
                                                    <td>{{ c.material_sku }}</td>
                                                    <td class="text-end">{{ c.required_qty }}</td>
                                                    <td class="text-end">{{ c.consumed_qty }}</td>
                                                    <td class="text-end">{{ formatMoney(c.unit_cost) }}</td>
                                                    <td class="text-end">{{ formatMoney(c.standard_cost) }}</td>
                                                    <td class="text-end">{{ formatMoney(c.actual_cost) }}</td>
                                                    <td class="text-end fw-semibold"
                                                        :class="c.variance > 0 ? 'text-danger' : c.variance < 0 ? 'text-success' : ''">
                                                        {{ formatMoney(c.variance) }}
                                                    </td>
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
import { onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { formatMoney } from '@/helpers/formatMoney.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import VMultiselect from '@/components/base/VMultiselect.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const adminSettingStore = useAdminSettingStore();
const { currentFiscalYear } = storeToRefs(adminSettingStore);

const rows = ref([]);
const summary = ref(null);
const warehouseOptions = ref([]);
const loading = ref(false);
const expandedRows = ref(new Set());

const filters = ref({
    from_date: '',
    to_date: '',
    warehouse_id: '',
});

function toggleExpand(id) {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
    expandedRows.value = new Set(expandedRows.value);
}

const loadReport = async () => {
    loading.value = true;
    expandedRows.value = new Set();
    try {
        const params = { ...filters.value };
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] === null) { delete params[k]; }
        });
        const res = await apiAdmin('inventory-report/production-variance', 'get', params);
        const data = res.data;
        rows.value = data.rows ?? [];
        summary.value = data.summary ?? null;
        if (data.warehouse_options?.length) {
            warehouseOptions.value = data.warehouse_options.map(w => ({ id: w.id, name: w.name }));
        }
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Order No', 'Product', 'SKU', 'Warehouse', 'Completed', 'Planned Qty', 'Produced Qty', 'Std Cost', 'Actual Cost', 'Variance'];
    const csvRows = rows.value.map(r => [
        r.order_no, r.finished_product, r.finished_sku, r.warehouse, r.completed_at,
        r.planned_qty, r.produced_qty, r.total_standard_cost, r.total_actual_cost, r.total_variance,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = 'production-variance-report.csv';
    a.click();
};

onMounted(async () => {
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date   = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), 0, 1).toISOString().slice(0, 10);
        filters.value.to_date   = now.toISOString().slice(0, 10);
    }
    await loadReport();
});
</script>
