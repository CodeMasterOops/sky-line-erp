<template>
    <PageHeader title="VAT Return (D3 / Anusuchi 13)" subtitle="Nepal IRD VAT Return" />

    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Fiscal Year</label>
                        <vue-select
                            v-model="filters.fiscal_year_id"
                            :options="fiscalYearOptions"
                            :reduce="opt => opt.value"
                            placeholder="Select Fiscal Year"
                        />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" v-model="filters.start_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" v-model="filters.end_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-primary w-100" @click="loadReport" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="data.period" class="row g-3 mb-3">
        <div class="col-md-12">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0">D3 VAT Return Summary – {{ data.period?.label }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">OUTPUT (Sales)</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Taxable Sales (Kar Laagne Bikri)</td>
                                        <td class="text-end fw-semibold">NPR {{ fmt(data.sales?.taxable_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Output VAT @ 13%</td>
                                        <td class="text-end fw-semibold text-danger">NPR {{ fmt(data.sales?.output_vat) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Exempt Sales (Kar Mukta Bikri)</td>
                                        <td class="text-end">NPR {{ fmt(data.sales?.exempt_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Zero-Rated Sales (Export)</td>
                                        <td class="text-end">NPR {{ fmt(data.sales?.zero_rated_amount) }}</td>
                                    </tr>
                                    <tr class="table-light fw-bold">
                                        <td>Total Sales</td>
                                        <td class="text-end">NPR {{ fmt(data.sales?.total_sales) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">INPUT (Purchases)</h6>
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Taxable Purchases</td>
                                        <td class="text-end fw-semibold">NPR {{ fmt(data.purchases?.taxable_amount) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Input VAT Claimed @ 13%</td>
                                        <td class="text-end fw-semibold text-success">NPR {{ fmt(data.purchases?.input_vat) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Exempt Purchases</td>
                                        <td class="text-end">NPR {{ fmt(data.purchases?.exempt_amount) }}</td>
                                    </tr>
                                    <tr class="table-light fw-bold">
                                        <td>Total Purchases</td>
                                        <td class="text-end">NPR {{ fmt(data.purchases?.total_purchases) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 ms-auto">
                            <div :class="['alert', data.net_vat_payable >= 0 ? 'alert-danger' : 'alert-success']">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ data.net_vat_payable >= 0 ? 'NET VAT PAYABLE (IRD)' : 'VAT CREDIT CARRY FORWARD' }}</strong>
                                    <strong class="fs-5">NPR {{ fmt(Math.abs(data.net_vat_payable)) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-if="data.sales_rows?.length" class="card border-0 mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Sales Details (Bikri Khata)</h6>
            <button class="btn btn-sm btn-outline-secondary" @click="exportCsv('sales')">
                <i class="ti ti-file-export me-1"></i> Export CSV
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Bijak No</th>
                            <th>Buyer Name</th>
                            <th>Buyer PAN</th>
                            <th class="text-end">Taxable Amount</th>
                            <th class="text-end">VAT (13%)</th>
                            <th class="text-end">Exempt</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in data.sales_rows" :key="row.bijak_no">
                            <td>{{ row.date }}</td>
                            <td>{{ row.bijak_no }}</td>
                            <td>{{ row.buyer_name }}</td>
                            <td>{{ row.buyer_pan }}</td>
                            <td class="text-end">{{ fmt(row.taxable_amount) }}</td>
                            <td class="text-end">{{ fmt(row.vat_amount) }}</td>
                            <td class="text-end">{{ fmt(row.exempt_amount) }}</td>
                            <td class="text-end fw-semibold">{{ fmt(row.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {useAccountingReportStore} from '@/stores/admin/accounting/report.js';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const reportStore = useAccountingReportStore();
const {vatReturn: vatReturnState} = storeToRefs(reportStore);

const filters = ref({ fiscal_year_id: null, start_date: null, end_date: null });
const loading = ref(false);
const data = ref({});
const fiscalYearOptions = ref([]);

const loadFiscalYears = async () => {
    try {
        const res = await apiAdmin('admin-setting/fiscal-year', 'get');
        fiscalYearOptions.value = (res.data.data || []).map(fy => ({
            label: fy.year_name,
            value: fy.id,
        }));
    } catch (e) { /* ignore */ }
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('account-report/vat-return', 'get', filters.value);
        data.value = res.data.data;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const fmt = (val) => {
    if (val === null || val === undefined) return '0.00';
    return Number(val).toLocaleString('en-NP', { minimumFractionDigits: 2 });
};

const exportCsv = (type) => {
    const rows = type === 'sales' ? data.value.sales_rows : data.value.purchase_rows;
    if (!rows?.length) return;
    const headers = Object.keys(rows[0]);
    const csv = [headers.join(','), ...rows.map(r => headers.map(h => `"${r[h] ?? ''}"`).join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `vat-${type}-register.csv`;
    a.click();
};

onMounted(() => { loadFiscalYears(); });
</script>
