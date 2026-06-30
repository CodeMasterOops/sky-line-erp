<template>
    <PageHeader hide-action-buttons title="Sales Tax Report" subtitle="Tax collected grouped by tax type with taxable amount breakdown" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div class="col-xl-6 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-info flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-info-subtle text-info">
                            <i class="ti ti-coins fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Taxable Amount</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.taxable_amount) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6 col-md-6 d-flex">
                <div class="card border-0 shadow-sm sale-widget widget-success flex-fill">
                    <div class="card-body d-flex align-items-center">
                        <span class="sale-icon bg-success-subtle text-success">
                            <i class="ti ti-receipt-tax fs-24"></i>
                        </span>
                        <div class="ms-3">
                            <p class="fw-medium mb-1">Tax Collected</p>
                            <h4 class="mb-0">{{ formatMoney(kpi.tax_amount) }}</h4>
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
                            label="Customer"
                            placeholder="All Customers"
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
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tax Type</th>
                                <th class="text-end">Rate (%)</th>
                                <th class="text-end">Invoices</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">Tax Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!rows.length && !loading">
                                <td colspan="5" class="text-center text-muted py-5">Set filters and click Generate to load data.</td>
                            </tr>
                            <tr v-for="(row, idx) in rows" :key="idx">
                                <td class="fw-semibold">{{ row.tax_name }}</td>
                                <td class="text-end">{{ row.tax_rate > 0 ? row.tax_rate + '%' : '-' }}</td>
                                <td class="text-end">{{ row.invoice_count }}</td>
                                <td class="text-end">{{ formatMoney(row.taxable_amount) }}</td>
                                <td class="text-end fw-semibold text-success">{{ formatMoney(row.tax_amount) }}</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="summary && rows.length" class="table-light">
                            <tr class="fw-bold">
                                <td colspan="3">Total</td>
                                <td class="text-end">{{ formatMoney(summary.taxable_amount) }}</td>
                                <td class="text-end text-success">{{ formatMoney(summary.tax_amount) }}</td>
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

const rows = ref([]);
const summary = ref(null);
const loading = ref(false);
const partyOptions = ref([]);

const adminSettingStore = useAdminSettingStore();
const {currentFiscalYear} = storeToRefs(adminSettingStore);

const filters = ref({ from_date: '', to_date: '', party_id: '' });

const kpi = computed(() => summary.value ?? { taxable_amount: 0, tax_amount: 0 });

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {...filters.value};
        Object.keys(params).forEach((k) => { if (!params[k]) { delete params[k]; } });
        const res = await apiAdmin('sales-report/sales-tax', 'get', params);
        const data = res.data.data;
        rows.value = data.rows || [];
        summary.value = data.summary;
        partyOptions.value = data.party_options || partyOptions.value;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const exportCsv = () => {
    if (!rows.value.length) { return; }
    const headers = ['Tax Type', 'Rate (%)', 'Invoices', 'Taxable Amount', 'Tax Amount'];
    const csvRows = rows.value.map(r => [r.tax_name, r.tax_rate, r.invoice_count, r.taxable_amount, r.tax_amount].map(v => `"${v ?? ''}"`).join(','));
    const csv = [headers.join(','), ...csvRows].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = 'sales-tax-report.csv';
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
