<template>
    <PageHeader hide-action-buttons title="Purchase Discount Report" subtitle="Line and order level discounts on bills" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-file-invoice fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Bills</p>
                            <h4 class="mb-0">{{ kpi.bill_count }}</h4>
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
                            <p class="fw-medium mb-1">Line Discount</p>
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
                            <p class="fw-medium mb-1">Order Discount</p>
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
                        <VMultiselect
                            id="party_id"
                            v-model="filters.party_id"
                            :options="partyOptions"
                            label="Supplier"
                            placeholder="All Suppliers"
                        />
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
                                <th>Supplier</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Line Discount</th>
                                <th class="text-end">Order Discount</th>
                                <th class="text-end">Total Discount</th>
                                <th class="text-end">Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="9" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="9" class="text-center text-muted py-4">No data found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td>{{ (pagination.current_page - 1) * pagination.per_page + i + 1 }}</td>
                                <td>{{ row.bill_no }}</td>
                                <td>{{ row.bill_date }}</td>
                                <td>{{ row.party_name }}</td>
                                <td class="text-end">{{ formatMoney(row.subtotal) }}</td>
                                <td class="text-end">{{ formatMoney(row.line_discount) }}</td>
                                <td class="text-end">{{ formatMoney(row.order_discount) }}</td>
                                <td class="text-end text-danger fw-semibold">{{ formatMoney(row.total_discount) }}</td>
                                <td class="text-end">{{ formatMoney(row.net_amount) }}</td>
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
const kpi = computed(() => reportData.value?.summary ?? {bill_count: 0, line_discount: 0, order_discount: 0, total_discount: 0});
const pagination = computed(() => reportData.value?.pagination ?? {current_page: 1, last_page: 1, per_page: 20});

const loadReport = async (page = 1) => {
    filters.value.page = page;
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/purchase-discount', 'get', params);
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
    const headers = ['Bill #', 'Date', 'Supplier', 'Subtotal', 'Line Discount', 'Order Discount', 'Total Discount', 'Net Amount'];
    const data = rows.value.map(r => [r.bill_no, r.bill_date, r.party_name, r.subtotal, r.line_discount, r.order_discount, r.total_discount, r.net_amount]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'purchase-discount.csv';
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
