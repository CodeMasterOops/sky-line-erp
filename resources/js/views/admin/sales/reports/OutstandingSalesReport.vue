<template>
    <PageHeader hide-action-buttons title="Outstanding Sales Report" subtitle="Unpaid and partially paid invoices with overdue tracking" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-file-invoice fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Outstanding Invoices</p>
                            <h4 class="mb-0">{{ kpi.invoice_count }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-wallet fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Balance Due</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.balance_due) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-alert-circle fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Overdue (page)</p>
                            <h4 class="mb-0">{{ kpi.overdue_count }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <select class="form-select" v-model="filters.party_id">
                            <option value="">All Customers</option>
                            <option v-for="p in partyOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="overdue_only" v-model="filters.overdue_only" />
                            <label class="form-check-label" for="overdue_only">Overdue only</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex gap-2 align-self-end">
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
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Customer</th>
                                <th class="text-end">Net Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-end">Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="8" class="text-center text-muted py-5">Set filters and click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx" :class="row.is_overdue ? 'table-danger' : ''">
                                <td class="fw-semibold text-nowrap">{{ row.invoice_no }}</td>
                                <td class="text-nowrap">{{ row.invoice_date }}</td>
                                <td class="text-nowrap">{{ row.due_date ?? '-' }}</td>
                                <td>{{ row.party_name }}</td>
                                <td class="text-end">{{ formatMoney(row.net_total) }}</td>
                                <td class="text-end text-success">{{ formatMoney(row.paid_total) }}</td>
                                <td class="text-end fw-bold text-warning">{{ formatMoney(row.balance_due) }}</td>
                                <td class="text-end">
                                    <span v-if="row.days_overdue > 0" class="badge bg-danger">{{ row.days_overdue }}d</span>
                                    <span v-else class="text-muted">-</span>
                                </td>
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
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const rows = ref([]);
const summary = ref(null);
const pagination = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const filters = ref({ party_id: '', overdue_only: false });

const kpi = computed(() => summary.value ?? { invoice_count: 0, balance_due: 0, overdue_count: 0 });

const loadReport = async (page = 1) => {
    loading.value = true;
    try {
        const params = {...filters.value, page};
        if (!params.party_id) { delete params.party_id; }
        if (!params.overdue_only) { delete params.overdue_only; }
        const res = await apiAdmin('sales-report/outstanding-sales', 'get', params);
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
    const headers = ['Invoice #', 'Invoice Date', 'Due Date', 'Customer', 'Net Total', 'Paid', 'Balance Due', 'Days Overdue'];
    const csvRows = rows.value.map(r => [r.invoice_no, r.invoice_date, r.due_date, r.party_name, r.net_total, r.paid_total, r.balance_due, r.days_overdue].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'outstanding-sales.csv';
    a.click();
};

onMounted(async () => {
    await loadReport();
});
</script>
