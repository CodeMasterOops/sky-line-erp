<template>
    <PageHeader hide-action-buttons title="Outstanding Purchase Report" subtitle="Bills with remaining balance due to suppliers" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-file-invoice fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Unpaid Bills</p>
                            <h4 class="mb-0">{{ kpi.bill_count }}</h4>
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
                            <p class="fw-medium mb-1">Balance Due</p>
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
                            <p class="fw-medium mb-1">Overdue Bills</p>
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
                        <VMultiselect
                            id="party_id"
                            v-model="filters.party_id"
                            :options="partyOptions"
                            label="Supplier"
                            placeholder="All Suppliers"
                        />
                    </div>
                    <div class="col-md-3 d-flex align-items-center pt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="overdue_only" v-model="filters.overdue_only" />
                            <label class="form-check-label" for="overdue_only">Overdue only</label>
                        </div>
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
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Bill #</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Supplier</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance Due</th>
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
                                <td colspan="9" class="text-center text-muted py-4">No outstanding bills found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i" :class="{'table-danger bg-opacity-10': row.is_overdue}">
                                <td>{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
                                <td>{{ row.bill_no }}</td>
                                <td>{{ row.bill_date }}</td>
                                <td>{{ row.due_date ?? '—' }}</td>
                                <td>{{ row.party_name }}</td>
                                <td class="text-end">{{ formatMoney(row.total_amount) }}</td>
                                <td class="text-end text-success">{{ formatMoney(row.paid_total) }}</td>
                                <td class="text-end fw-semibold text-warning">{{ formatMoney(row.balance_due) }}</td>
                                <td>
                                    <span v-if="row.is_overdue" class="badge bg-danger-subtle text-danger">Overdue</span>
                                    <span v-else class="badge bg-warning-subtle text-warning">Pending</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="pagination.last_page > 1" class="d-flex justify-content-end p-3">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{disabled: pagination.current_page === 1}">
                                <button class="page-link" @click="changePage(pagination.current_page - 1)">&laquo;</button>
                            </li>
                            <li v-for="p in pagination.last_page" :key="p" class="page-item" :class="{active: p === pagination.current_page}">
                                <button class="page-link" @click="changePage(p)">{{ p }}</button>
                            </li>
                            <li class="page-item" :class="{disabled: pagination.current_page === pagination.last_page}">
                                <button class="page-link" @click="changePage(pagination.current_page + 1)">&raquo;</button>
                            </li>
                        </ul>
                    </nav>
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

const reportData = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const filters = ref({party_id: '', overdue_only: false, page: 1});

const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => reportData.value?.summary ?? {bill_count: 0, balance_due: 0, overdue_count: 0});
const pagination = computed(() => reportData.value?.pagination ?? {current_page: 1, last_page: 1, per_page: 20});

const loadReport = async (page = 1) => {
    filters.value.page = page;
    loading.value = true;
    try {
        const params = {...filters.value};
        if (!params.party_id) { delete params.party_id; }
        if (!params.overdue_only) { delete params.overdue_only; }
        const res = await apiAdmin('purchase-report/outstanding-purchase', 'get', params);
        reportData.value = res.data.data;
        partyOptions.value = reportData.value?.party_options ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => { loadReport(page); };

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Bill #', 'Date', 'Due Date', 'Supplier', 'Total', 'Paid', 'Balance Due', 'Overdue'];
    const data = rows.value.map(r => [r.bill_no, r.bill_date, r.due_date ?? '', r.party_name, r.total_amount, r.paid_total, r.balance_due, r.is_overdue ? 'Yes' : 'No']);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'outstanding-purchase.csv';
    a.click();
};

onMounted(async () => { await loadReport(); });
</script>
