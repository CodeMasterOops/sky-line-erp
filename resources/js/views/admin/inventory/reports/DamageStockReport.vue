<template>
    <PageHeader hide-action-buttons title="Damage Stock Report" subtitle="History of stock write-offs and damage reports" />

    <section class="section">
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <VDatepicker id="damage_from_date" label="From Date" v-model="filters.from_date" />
                    </div>
                    <div class="col-md-2">
                        <VDatepicker id="damage_to_date" label="To Date" v-model="filters.to_date" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Warehouse</label>
                        <VMultiselect
                            id="damage_warehouse_id"
                            v-model="filters.warehouse_id"
                            :options="warehouseStore.optionsTree"
                            :loading="warehouseStore.warehouses.loading"
                            placeholder="All Warehouses"
                        />
                    </div>
                    <div class="col-md-2">
                        <VMultiselect
                            id="status"
                            v-model="filters.status"
                            :options="statusOptions"
                            label="Status"
                            placeholder="All"
                        />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Reference</label>
                        <input
                            v-model="filters.search"
                            type="search"
                            class="form-control"
                            placeholder="Search reference"
                        />
                    </div>
                    <div class="col-md-1 d-flex gap-2">
                        <button class="btn btn-success flex-grow-1" @click="loadReport(1)" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm"></span>
                            <span v-else><i class="ti ti-search"></i></span>
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
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger"><i class="ti ti-alert-triangle fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Reports</p>
                            <h4 class="mb-0">{{ summary.total_reports }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning"><i class="ti ti-file-check fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Approved</p>
                            <h4 class="mb-0">{{ summary.approved_reports }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-secondary-subtle text-secondary"><i class="ti ti-file-time fs-24"></i></span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Pending Approval</p>
                            <h4 class="mb-0">{{ summary.pending_reports }}</h4>
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
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Warehouse</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Approved At</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="7" class="text-center text-muted py-4">
                                    Set filters and click Search to load data.
                                </td>
                            </tr>
                            <tr v-if="loading">
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border text-primary spinner-border-sm"></div>
                                </td>
                            </tr>
                            <tr v-for="row in rows" :key="row.id">
                                <td class="text-nowrap">{{ row.date }}</td>
                                <td class="fw-medium">{{ row.reference_no || '—' }}</td>
                                <td>{{ row.warehouse }}</td>
                                <td>{{ row.reason || '—' }}</td>
                                <td>
                                    <span class="badge" :class="row.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                        {{ row.status }}
                                    </span>
                                </td>
                                <td class="text-nowrap">{{ row.approved_at ? row.approved_at.slice(0, 10) : '—' }}</td>
                                <td class="text-muted small">{{ row.remarks || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination && pagination.last_page > 1"
                    class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">
                        Page {{ pagination.current_page }} of {{ pagination.last_page }}
                        ({{ pagination.total }} records)
                    </small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary"
                            :disabled="pagination.current_page <= 1"
                            @click="loadReport(pagination.current_page - 1)">‹ Prev</button>
                        <button class="btn btn-outline-secondary"
                            :disabled="pagination.current_page >= pagination.last_page"
                            @click="loadReport(pagination.current_page + 1)">Next ›</button>
                    </div>
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
import { useWarehouseStore } from '@/stores/admin/inventory/warehouse.js';
import { useAdminSettingStore } from '@/stores/admin/settings/admin-setting.js';
import VMultiselect from '@/components/base/VMultiselect.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';

const warehouseStore = useWarehouseStore();
const adminSettingStore = useAdminSettingStore();
const { currentFiscalYear } = storeToRefs(adminSettingStore);

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const loading = ref(false);

const statusOptions = [
    { id: 'draft', name: 'Draft' },
    { id: 'approved', name: 'Approved' },
];

const filters = ref({
    from_date: '',
    to_date: '',
    warehouse_id: '',
    status: '',
    search: '',
});

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = { ...filters.value, page, limit: 25 };
        Object.keys(params).forEach((k) => {
            if (params[k] === '' || params[k] === null) { delete params[k]; }
        });
        const res = await apiAdmin('damage-report', 'get', params);
        rows.value = res.data.data ?? [];
        const meta = res.data.meta ?? {};
        pagination.value = {
            current_page: meta.current_page ?? 1,
            last_page:    meta.last_page ?? 1,
            total:        meta.total ?? rows.value.length,
        };
        summary.value = {
            total_reports:   pagination.value.total,
            approved_reports: rows.value.filter(r => r.status === 'approved').length,
            pending_reports:  rows.value.filter(r => r.status !== 'approved').length,
        };
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Date', 'Reference', 'Warehouse', 'Reason', 'Status', 'Approved At', 'Remarks'];
    const csvRows = rows.value.map(r => [
        r.date, r.reference_no, r.warehouse, r.reason, r.status,
        r.approved_at?.slice(0, 10) ?? '', r.remarks,
    ].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = 'damage-stock-report.csv';
    a.click();
};

onMounted(async () => {
    warehouseStore.getWarehouses();
    await adminSettingStore.getCurrentFiscalYear();
    const fy = currentFiscalYear.value?.data;
    if (fy?.start_date && fy?.end_date) {
        filters.value.from_date = fy.start_date;
        filters.value.to_date   = fy.end_date;
    } else {
        const now = new Date();
        filters.value.from_date = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
        filters.value.to_date   = now.toISOString().slice(0, 10);
    }
    await loadReport(1);
});
</script>
