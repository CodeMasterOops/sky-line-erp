<template>
    <PageHeader hide-action-buttons title="Purchase Summary Report" subtitle="High-level KPIs for purchases, returns, and net spend for a period" />

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
                <div class="card border-0 shadow-sm sale-widget widget-danger flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-danger-subtle text-danger">
                            <i class="ti ti-receipt-refund fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Returns</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.total_returns) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-warning flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-warning-subtle text-warning">
                            <i class="ti ti-trending-up fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Net Spend</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.net_spend) }}</h4>
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
                        <button class="btn btn-outline-secondary" @click="exportCsv" :disabled="!reportData" title="Export CSV">
                            <i class="ti ti-file-export"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="reportData" class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <td class="text-muted w-50">Period</td>
                                <td class="fw-semibold">{{ reportData.period.label }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Bills</td>
                                <td>{{ reportData.summary.bill_count }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Subtotal (before discount)</td>
                                <td>{{ formatMoney(reportData.summary.subtotal) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total Discount</td>
                                <td class="text-danger">- {{ formatMoney(reportData.summary.total_discount) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tax Amount</td>
                                <td>{{ formatMoney(reportData.summary.tax_amount) }}</td>
                            </tr>
                            <tr class="table-light fw-semibold">
                                <td>Net Purchases</td>
                                <td>{{ formatMoney(reportData.summary.net_purchases) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Debit Notes / Returns ({{ reportData.summary.return_count }})</td>
                                <td class="text-danger">- {{ formatMoney(reportData.summary.total_returns) }}</td>
                            </tr>
                            <tr class="table-success fw-bold">
                                <td>Net Spend</td>
                                <td>{{ formatMoney(reportData.summary.net_spend) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-else-if="!loading" class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-5">Set filters and click Generate to load data.</div>
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

const filters = ref({from_date: '', to_date: '', party_id: ''});

const kpi = computed(() => reportData.value?.summary ?? {bill_count: 0, net_purchases: 0, total_returns: 0, net_spend: 0});

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('purchase-report/purchase-summary', 'get', params);
        reportData.value = res.data.data;
        partyOptions.value = reportData.value?.party_options ?? [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!reportData.value) { return; }
    const s = reportData.value.summary;
    const rows = [
        ['Metric', 'Value'],
        ['Period', reportData.value.period.label],
        ['Bill Count', s.bill_count],
        ['Subtotal', s.subtotal],
        ['Total Discount', s.total_discount],
        ['Tax Amount', s.tax_amount],
        ['Net Purchases', s.net_purchases],
        ['Return Count', s.return_count],
        ['Total Returns', s.total_returns],
        ['Net Spend', s.net_spend],
    ];
    const csv = rows.map(r => r.map(v => `"${v ?? ''}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'purchase-summary.csv';
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
