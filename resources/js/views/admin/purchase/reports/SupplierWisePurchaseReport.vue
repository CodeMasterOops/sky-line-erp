<template>
    <PageHeader hide-action-buttons title="Supplier Wise Purchase Report" subtitle="Purchase totals broken down by supplier" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
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
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-currency-rupee fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Net Purchases</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.net_purchases) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-circle-check fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Total Paid</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.paid) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-wallet fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Outstanding</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.outstanding) }}</h4>
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
                                <th>Supplier</th>
                                <th>Bills</th>
                                <th class="text-end">Net Purchases</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm me-2"></div>Loading...
                                </td>
                            </tr>
                            <tr v-else-if="!rows.length">
                                <td colspan="6" class="text-center text-muted py-4">No data found.</td>
                            </tr>
                            <tr v-for="(row, i) in rows" :key="i">
                                <td>{{ i + 1 }}</td>
                                <td>{{ row.party_name }}</td>
                                <td>{{ row.bill_count }}</td>
                                <td class="text-end">{{ formatMoney(row.net_purchases) }}</td>
                                <td class="text-end text-success">{{ formatMoney(row.paid) }}</td>
                                <td class="text-end" :class="row.outstanding > 0 ? 'text-warning fw-semibold' : ''">{{ formatMoney(row.outstanding) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="rows.length" class="table-light fw-semibold">
                            <tr>
                                <td colspan="2">Total</td>
                                <td>{{ kpi.bill_count }}</td>
                                <td class="text-end">{{ formatMoney(kpi.net_purchases) }}</td>
                                <td class="text-end">{{ formatMoney(kpi.paid) }}</td>
                                <td class="text-end">{{ formatMoney(kpi.outstanding) }}</td>
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

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({from_date: '', to_date: ''});

const rows = computed(() => reportData.value?.rows ?? []);
const kpi = computed(() => reportData.value?.summary ?? {bill_count: 0, net_purchases: 0, paid: 0, outstanding: 0});

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/supplier-wise-purchase', 'get', params);
        reportData.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Supplier', 'Bills', 'Net Purchases', 'Paid', 'Outstanding'];
    const data = rows.value.map(r => [r.party_name, r.bill_count, r.net_purchases, r.paid, r.outstanding]);
    const csv = [headers, ...data].map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'supplier-wise-purchase.csv';
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
