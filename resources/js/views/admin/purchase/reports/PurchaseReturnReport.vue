<template>
    <PageHeader hide-action-buttons title="Purchase Return Report" subtitle="Debit notes issued to suppliers" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-receipt-refund fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Return Count</p>
                            <h4 class="mb-0">{{ kpi.return_count }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-coins fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Subtotal</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.subtotal) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-tag-off fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Discount</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_discount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Amount</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_amount) }}</h4>
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
                        <VMultiselect
                            id="party_id"
                            v-model="filters.party_id"
                            :options="partyOptions"
                            label="Supplier"
                            placeholder="All Suppliers"
                        />
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
                                <th>Debit Note #</th>
                                <th>Date</th>
                                <th>Supplier</th>
                                <th>Bill #</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="9" class="text-center text-muted py-4">No returns found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td>{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
                                <td>{{ row.debit_note_no }}</td>
                                <td>{{ row.note_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.bill_no ?? '—' }}</td>
                                <td class="text-end">{{ formatMoney(row.subtotal) }}</td>
                                <td class="text-end">{{ formatMoney(row.total_discount) }}</td>
                                <td class="text-end">{{ formatMoney(row.tax_amount) }}</td>
                                <td class="text-end fw-semibold">{{ formatMoney(row.total_amount) }}</td>
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
import {storeToRefs} from 'pinia';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {useAdminSettingStore} from '@/stores/admin/settings/admin-setting.js';

const reportData = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({from_date: '', to_date: '', party_id: '', page: 1});

const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => reportData.value?.summary ?? {return_count: 0, subtotal: 0, total_discount: 0, total_amount: 0});
const pagination = computed(() => reportData.value?.pagination ?? {current_page: 1, last_page: 1, per_page: 20});

const loadReport = async (page = 1) => {
    filters.value.page = page;
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/purchase-return', 'get', params);
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
    const headers = ['Debit Note #', 'Date', 'Supplier', 'Bill #', 'Subtotal', 'Discount', 'Tax', 'Total'];
    const data = rows.value.map(r => [r.debit_note_no, r.note_date, r.party_name, r.bill_no ?? '', r.subtotal, r.total_discount, r.tax_amount, r.total_amount]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'purchase-return.csv';
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
