<template>
    <PageHeader hide-action-buttons title="Discount Report" subtitle="Invoices with line-level and order-level discounts for the selected period" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-file-invoice fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Invoices with Discount</p>
                            <h4 class="mb-0">{{ kpi.invoice_count }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-tag-off fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Line Discounts</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.line_discount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-discount fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Order Discounts</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.order_discount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-circle-minus fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Discount</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_discount) }}</h4>
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
                        <label class="form-label">Customer</label>
                        <select class="form-select" v-model="filters.party_id">
                            <option value="">All Customers</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-success w-100" @click="loadReport(1)" :disabled="loading">
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
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Line Disc.</th>
                                <th class="text-end">Order Disc.</th>
                                <th class="text-end">Total Disc.</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="9" class="text-center text-muted py-5">Set filters and click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td class="fw-semibold text-nowrap">{{ row.invoice_no }}</td>
                                <td class="text-nowrap">{{ row.invoice_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td class="text-end">{{ formatMoney(row.subtotal) }}</td>
                                <td class="text-end text-danger">{{ formatMoney(row.line_discount) }}</td>
                                <td class="text-end text-danger">{{ formatMoney(row.order_discount) }}</td>
                                <td class="text-end fw-semibold text-danger">{{ formatMoney(row.total_discount) }}</td>
                                <td class="text-end">{{ formatMoney(row.tax_amount) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.net_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination && pagination.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                    <small class="text-muted">Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} records)</small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="loadReport(pagination.current_page - 1)">‹ Prev</button>
                        <button class="btn btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="loadReport(pagination.current_page + 1)">Next ›</button>
                    </div>
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

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({ from_date: '', to_date: '', party_id: '' });

const kpi = computed(() => summary.value ?? { invoice_count: 0, line_discount: 0, order_discount: 0, total_discount: 0 });

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = {...filters.value, page};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('sales-report/discount-report', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        pagination.value = data.pagination;
        partyOptions.value = data.party_options || partyOptions.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Invoice #', 'Date', 'Customer', 'Subtotal', 'Line Disc.', 'Order Disc.', 'Total Disc.', 'Tax', 'Net Amount'];
    const csvRows = rows.value.map(r => [r.invoice_no, r.invoice_date, r.party_name, r.subtotal, r.line_discount, r.order_discount, r.total_discount, r.tax_amount, r.net_amount].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'discount-report.csv';
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
